<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Quill\Routing\Router;
use Quill\Request;
use Quill\Routing\RouteMatch;
use Quill\Validation\Validator;
use Quill\Runtime\Json;

/**
 * Quill Runtime Server handling multi-worker orchestration via pcntl_fork.
 */
final class Server
{
    private Router $router;
    private int $port = 8080;
    /** @var mixed */
    private $validator;
    private bool $running = true;

    private DriverInterface $driver;

    protected ?\Psr\Log\LoggerInterface $logger;
    protected int $dtoBufferSize;
    protected int $errorBufferSize;

    public function __construct(
        Router $router,
        ?DriverInterface $driver = null,
        ?\Psr\Log\LoggerInterface $logger = null,
        int $dtoBufferSize = 65536,
        int $errorBufferSize = 4096
    ) {
        $this->router = $router;
        $this->driver = $driver ?: Runtime::getDriver();
        $this->logger = $logger;
        $this->dtoBufferSize = $dtoBufferSize;
        $this->errorBufferSize = $errorBufferSize;
    }

    // ── Public entry-point ────────────────────────────────────────────────────

    public function start(int $port = 8080): void
    {
        $this->port = $port;

        $workersCount = (int) (getenv('QUILL_WORKERS') ?: 1);
        $logPath = getenv('QUILL_LOG') ?: 'off';
        
        if ($logPath === 'true' || $logPath === '1') {
            $logPath = 'storage/logs/quill.log';
        }

        echo "  \x1B[1mPort\x1B[0m       : \x1B[35m{$this->port}\x1B[0m\n";
        echo "  \x1B[1mWorkers\x1B[0m    : \x1B[35m{$workersCount}\x1B[0m\n";
        echo "  \x1B[1mRuntime\x1B[0m    : \x1B[32mRust Fast-Path\x1B[0m\n";
        echo "  \x1B[1mLogging\x1B[0m    : \x1B[36m{$logPath}\x1B[0m\n\n";

        $nWorkers = max(1, (int) (getenv('QUILL_WORKERS') ?: 1));

        if ($nWorkers > 1 && function_exists('pcntl_fork')) {
            try {
                $fd = $this->driver->prebind($port);
                if ($fd < 0) {
                    throw new \RuntimeException("Failed to pre-bind port {$port}. Is it already in use?");
                }
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable) {
                // Fallback: Bind inside worker via SO_REUSEPORT if pre-bind fails
            }
            $this->spawnWorkers($nWorkers);
        } else {
            $this->setupSignals([]);
            try {
                $this->bootWorker();
            } catch (\Throwable $e) {
                fwrite(STDERR, "[Worker " . getmypid() . "] Boot failed: {$e->getMessage()}\n");
                exit(1);
            }
            $this->runEventLoop();
        }
    }

    // ── Multi-worker forking ──────────────────────────────────────────────────

    /**
     * Fork (nWorkers - 1) children, then run the parent as worker[0].
     * All Rust resources are initialised AFTER the fork so each process owns
     * an independent copy — no shared Arc references across process boundaries.
     */
    private function spawnWorkers(int $nWorkers): void
    {
        $pids = [];

        for ($i = 1; $i < $nWorkers; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed — run with however many workers we have so far.
                break;
            }

            if ($pid === 0) {
                // ── Child process ─────────────────────────────────────────────
                // Block signals until Rust heap is owned by this process
                pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT]);
                try {
                    $this->setupSignals([]);
                    $this->bootWorker();

                    // Unblock signals before entering the event loop
                    pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                    $this->runEventLoop(true);
                } catch (\Throwable $e) {
                    fwrite(STDERR, "[Worker " . getmypid() . "] Boot failed: {$e->getMessage()}\n");
                    exit(1);
                } finally {
                    // Ensure signals are unblocked before exit if boot fails
                    pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT]);
                }
                exit(0);
            }

            $pids[] = $pid;
        }

        // ── Parent process ────────────────────────────────────────────────────
        $this->setupSignals($pids);
        try {
            $this->bootWorker();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[Worker " . getmypid() . "] Boot failed: {$e->getMessage()}\n");
            // Kill children before exiting
            foreach ($pids as $pid) {
                posix_kill($pid, SIGTERM);
            }
            exit(1);
        }
        $this->runEventLoop();

        // Reap any remaining children after the parent's loop exits.
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }

    // ── Per-process initialisation ────────────────────────────────────────────

    /**
     * Boot the Rust resources for this specific process.
     * Safe to call in both parent and child because compile()/reinitialize()
     * create brand-new Rust objects in this process's heap.
     */
    private function bootWorker(): void
    {
        // Validator must be reinitialized BEFORE router recompile so that
        // Router::compile() registers DTOs with the new Rust registry.
        Validator::reinitialize();

        // Recompile builds a fresh Arc<QuillRouter> in this process.
        $this->router->recompile();

        $this->validator = Validator::getRegistry();
    }

    /**
     * Pre-register static route responses with the Rust engine.
     *
     * For every parameterless, DTO-free route whose handler is a simple static
     * method or Closure, we execute it once and hand the serialized response to
     * Rust via quill_route_preload(). Subsequent HTTP requests for that handler
     * are served entirely within Rust — no PHP round-trip, no channel overhead.
     *
     * Failure to preload any route is non-fatal; the PHP bridge remains as the
     * fallback for routes not successfully preloaded.
     */
    private function preloadRoutes(bool $silent = false): void
    {
        $routes = $this->router->getRoutes();
        $paramCache = $this->router->getParamCache();
        $request = new Request();
        $preloadedCount = 0;

        foreach ($routes as $handlerId => $routeInfo) {
            // Only preload routes that have no path parameters recorded in the manifest.
            $handler = $routeInfo[2];

            $key = $this->resolveHandlerKey($handler);
            $hasDTO = false;
            
            if ($key !== null && isset($paramCache[$key])) {
                foreach ($paramCache[$key] as $param) {
                    if ($param['isDTO']) {
                        $hasDTO = true;
                        break;
                    }
                }
            }

            if ($hasDTO) {
                continue;
            }

            try {
                $request->reset();

                // Execute the handler once to capture the static response.
                if ($handler instanceof \Closure) {
                    $result = $handler($request);
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                    if (!is_callable([$class, $method])) {
                        continue;
                    }
                    $result = $class::$method();
                } else {
                    continue;
                }

                // Build the same response envelope that the polling loop produces.
                $resultBody = is_string($result) ? $result : Json::encode($result ?? []);

                $responseJson = Json::encode([
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Powered-By' => 'Quill',
                    ],
                    'body' => $resultBody,
                ]);

                if ($responseJson === '') {
                    continue;
                }

                // ⚡ Push to Rust Core
                $this->driver->preloadResponse((int) $handlerId, $responseJson);
                $preloadedCount++;
            } catch (\Throwable) {
                // Non-fatal: this route will be served via the PHP bridge.
            }
        }

        if (!$silent && $preloadedCount > 0) {
            echo "  \x1B[1mPreloading\x1B[0m : \x1B[32mDONE\x1B[0m — \x1B[1m{$preloadedCount}\x1B[0m routes optimized via Fast Path\n";
        }
    }

    /**
     * Resolve a unique key for a route handler.
     */
    private function resolveHandlerKey(mixed $handler): ?string
    {
        if (is_array($handler) && count($handler) === 2 && isset($handler[0], $handler[1]) && is_scalar($handler[0]) && is_scalar($handler[1])) {
            return "{$handler[0]}::{$handler[1]}";
        } elseif ($handler instanceof \Closure) {
            return 'closure_' . spl_object_id($handler);
        }
        return null;
    }


    // ── Signal handling ───────────────────────────────────────────────────────

    /**
     * @param list<int> $childPids  PIDs to forward SIGINT/SIGTERM to (parent only).
     */
    private function setupSignals(array $childPids): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function () use ($childPids): void {
            fwrite(STDOUT, "\n[Process " . getmypid() . "] Received stop signal. Draining queue...\n");
            $this->running = false;
            try {
                $this->driver->drain(100); // 100ms drain timeout
            } catch (\Throwable) {
            }

            foreach ($childPids as $pid) {
                posix_kill($pid, SIGTERM);
            }

            // Allow a small window for children to reap if we are the parent
            if (!empty($childPids)) {
                usleep(50000); // 50ms
            }

            exit(0);
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);

        // Reap zombie children automatically (parent only).
        if (!empty($childPids)) {
            pcntl_signal(SIGCHLD, function (): void {
                while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
                    // reaped
                }
            });
        }
    }

    // ── Hot polling loop ──────────────────────────────────────────────────────

    private function runEventLoop(bool $silent = false): void
    {
        $handle = $this->router->getHandle();

        if ($handle === null) {
            throw new \RuntimeException('Quill Router handle not initialized.');
        }

        /** @phpstan-ignore-next-line */
        $res = (int) $this->driver->listen($handle, $this->validator, $this->port, (int) (getenv('QUILL_WORKERS') ?: 0), 10000);
        if ($res !== 0) {
            throw new \RuntimeException("Failed to listen on port {$this->port} (Error Code: {$res}). Possible cause: Port already in use or insufficient permissions.");
        }

        // Pre-register static route responses with Rust before entering the hot loop.
        $this->preloadRoutes($silent);

        if (!$silent) {
            echo "  \x1B[1mListening\x1B[0m  : \x1B[32mhttp://0.0.0.0:{$this->port}\x1B[0m\n\n";
        }

        // Pre-allocate FFI buffers once — reused for every request.
        $idBuf = $this->driver->allocateIdBuffer();
        $handlerIdBuf = $this->driver->allocateHandlerIdBuffer();
        $paramsBuf = $this->driver->allocateParamsBuffer($this->errorBufferSize);
        $dtoBuf = $this->driver->allocateDtoBuffer($this->dtoBufferSize);

        $id = 0;
        $request = new Request();
        $paramCache = $this->router->getParamCache();
        $container = $this->router->getContainer();
        $routes = $this->router->getRoutes();

        while ($this->running) {
            /** @phpstan-ignore-next-line */
            $hasRequest = $this->driver->poll($idBuf, $handlerIdBuf, $paramsBuf, $this->errorBufferSize, $dtoBuf, $this->dtoBufferSize);

            if ($hasRequest === -1) {
                fwrite(STDERR, "[Worker " . getmypid() . "] Native engine panic in poll(). Restarting worker loop...\n");
                continue;
            }

            if ($hasRequest === 1) {
                $startTime = microtime(true);
                try {
                    /** @phpstan-ignore-next-line */
                    $id = (int) $idBuf[0];
                    /** @phpstan-ignore-next-line */
                    $handlerId = (int) $handlerIdBuf[0];

                    $paramsJson = (string)$this->driver->getString($paramsBuf);
                    $dtoDataJson = (string)$this->driver->getString($dtoBuf);

                    if ($paramsJson !== '' && str_contains($paramsJson, '"path":"/__quill/metrics"')) {
                        $this->driver->respond($id, $this->driver->stats());
                        continue;
                    }
                    if ($paramsJson !== '' && str_contains($paramsJson, '"path":"/__quill/health"')) {
                        $this->driver->respond($id, '{"status":"ok"}');
                        continue;
                    }

                    if (strlen($dtoDataJson) >= $this->dtoBufferSize - 1) {
                        if ($this->logger)
                            $this->logger->warning("[Worker " . getmypid() . "] DTO buffer saturated.");
                        else
                            fwrite(STDERR, "[Worker " . getmypid() . "] Warning: DTO buffer saturated.\n");
                    }

                    $params = Json::decode($paramsJson);
                    $dtoData = ($dtoDataJson !== 'null' && $dtoDataJson !== '')
                        ? Json::decode($dtoDataJson)
                        : null;

                    /** @var Request $request */
                    $request->reset();

                    // Extract metadata from Rust
                    $actualMethod = is_string($params['_method'] ?? null) ? $params['_method'] : 'GET';
                    $actualPath = is_string($params['_path'] ?? null) ? $params['_path'] : '/';
                    $clientIp = is_string($params['_ip'] ?? null) ? $params['_ip'] : '127.0.0.1';

                    if (isset($params['_body']) && is_string($params['_body'])) {
                        $request->setRawInput($params['_body']);
                    }
                    unset($params['_method'], $params['_path'], $params['_ip'], $params['_body']);

                    $_SERVER['REMOTE_ADDR'] = $clientIp;
                    /** @var array<string, string> $params */
                    $request->setPathVars($params);
                    $request = $request->withMethod($actualMethod)->withPath($actualPath);

                    // Fast Path Check: Simple Closure with no complex dependencies
                    $handler = $routes[(int) $handlerId][2];
                    if ($handler instanceof \Closure && empty($dtoData) && empty($paramCache['closure_' . spl_object_id($handler)])) {
                        $result = $handler($request);
                        $resultBody = is_string($result) ? $result : json_encode($result ?? []);
                        $status = 200;
                        $headers = [
                            'Content-Type' => 'application/json',
                            'X-Powered-By' => 'Quill/FastPath',
                        ];
                    } else {
                        /** @var array{int, array<string>|(callable(): mixed), array<string, string>} $info */
                        $info = [1, $handler, $params];
                        $routeMatch = new RouteMatch(
                            $info,
                            $paramCache,
                            [],
                            $container,
                            $dtoData
                        );

                        ob_start();
                        try {
                            $result = $routeMatch->execute($request);
                            $bodyContent = ob_get_clean();
                        } catch (\Throwable $e) {
                            if (ob_get_level() > 0)
                                ob_end_clean();
                            throw $e;
                        }

                        $status = 200;
                        $headers = [
                            'Content-Type' => 'application/json',
                            'X-Powered-By' => 'Quill',
                        ];

                        if ($result instanceof \Quill\Http\Response) {
                            $status = $result->getStatus();
                            $headers = array_merge($headers, $result->getHeaders());
                            $resultBody = (string) $bodyContent;
                        } else {
                            $resultBody = (string) json_encode($result ?? []);
                        }
                    }

                    $response = [
                        'status' => $status,
                        'headers' => $headers,
                        'body' => $resultBody,
                    ];

                    $json = Json::encode($response);
                    /** @phpstan-ignore-next-line */
                    $res = (int) $this->driver->respond($id, $json);

                    if ($this->logger instanceof \Quill\Logger || $this->logger instanceof \Quill\Runtime\MultiLogger) {
                        $duration = (microtime(true) - $startTime) * 1000;
                        /** @phpstan-ignore-next-line */
                        $this->logger->access(
                            $clientIp,
                            $request->method(),
                            $request->path(),
                            'HTTP/1.1',
                            $status,
                            strlen((string)$resultBody),
                            '-',
                            '-',
                            $duration
                        );
                    }

                    if ($res === -1) {
                        if ($this->logger)
                            $this->logger->error("[Worker " . getmypid() . "] Failed to send response: Native engine error.");
                        else
                            fwrite(STDERR, "[Worker " . getmypid() . "] Failed to send response: Native engine error.\n");
                    }
                } catch (\Throwable $e) {
                    if ($this->logger)
                        $this->logger->error("[Worker " . getmypid() . "] Execution error: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                    else
                        fwrite(STDERR, "[Worker " . getmypid() . "] Execution error: {$e->getMessage()}\n{$e->getTraceAsString()}\n");

                    $errJson = Json::encode([
                        'status' => 500,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => Json::encode(['error' => 'Internal Server Error', 'detail' => $e->getMessage()])
                    ]);
                    $this->driver->respond((int) $id, $errJson);
                }
            }
        }
    }
}
