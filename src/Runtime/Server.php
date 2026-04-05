<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Quill\Routing\Router;
use Quill\Http\Request;
use Quill\Routing\RouteMatch;
use Quill\Validation\Validator;

/**
 * Quill Runtime Server
 *
 * Multi-worker architecture:
 *  1. The parent forks (QUILL_WORKERS - 1) child processes BEFORE any Rust
 *     resources are created.
 *  2. Each process (parent + children) independently calls recompile() and
 *     reinitialize() so every worker owns its own Arc<QuillRouter> and
 *     Arc<ValidatorRegistry> in its own Rust heap.
 *  3. SO_REUSEPORT lets every worker bind the same TCP port; the kernel
 *     distributes incoming connections between them.
 *  4. Each worker runs its own tight polling loop.
 */
final class Server
{
    private Router $router;
    private int $port = 8080;
    /** @var mixed */
    private $validator;
    private bool $running = true;

    private DriverInterface $driver;

    public function __construct(Router $router, ?DriverInterface $driver = null)
    {
        $this->router = $router;
        $this->driver = $driver ?: Runtime::getDriver();
    }

    // ── Public entry-point ────────────────────────────────────────────────────

    public function start(int $port = 8080): void
    {
        $this->port = $port;
        $nWorkers   = max(1, (int) (getenv('QUILL_WORKERS') ?: 1));

        if ($nWorkers > 1 && function_exists('pcntl_fork')) {
            // Attempt to pre-bind the TCP socket ONCE before forking so every
            // worker shares the same kernel accept queue (optimal path).
            // quill_server_prebind was added after the initial quill-core release,
            // so we catch FFI\Exception and fall back gracefully: each worker will
            // bind its own SO_REUSEPORT socket via the Rust make_listener() path.
            try {
                $fd = $this->driver->prebind($port);
                if ($fd < 0) {
                    throw new \RuntimeException("Failed to pre-bind port {$port}. Is it already in use?");
                }
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable) {
                // quill_server_prebind not available in this build of quill-core.
                // Workers will each bind independently using SO_REUSEPORT.
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
                    $this->runEventLoop();
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
            foreach ($childPids as $pid) {
                posix_kill($pid, SIGTERM);
            }
            $this->running = false;
        };

        pcntl_signal(SIGINT,  $stop);
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

    private function runEventLoop(): void
    {
        $handle = $this->router->getHandle();

        if ($handle === null) {
            throw new \RuntimeException('Quill Router handle not initialized.');
        }

        /** @phpstan-ignore-next-line */
        $res = $this->driver->listen($handle, $this->validator, $this->port);
        if ($res !== 0) {
            throw new \RuntimeException("Failed to listen on port {$this->port} (code: {$res})");
        }

        echo '[Worker ' . getmypid() . "] listening on http://0.0.0.0:{$this->port}\n";

        // Pre-allocate FFI buffers once — reused for every request.
        $idBuf        = $this->driver->allocateIdBuffer();
        $handlerIdBuf = $this->driver->allocateHandlerIdBuffer();
        $paramsBuf    = $this->driver->allocateParamsBuffer(4096);
        $dtoBuf       = $this->driver->allocateDtoBuffer(65536);

        $id = 0;
        while ($this->running) {
            /** @phpstan-ignore-next-line */
            $hasRequest = $this->driver->poll($idBuf, $handlerIdBuf, $paramsBuf, 4096, $dtoBuf, 65536);

            if ($hasRequest === 1) {
                try {
                    /** @var \ArrayAccess<int, int> $idBuf */
                    $id        = (int)$idBuf[0];
                    /** @var \ArrayAccess<int, int> $handlerIdBuf */
                    $handlerId = (int)$handlerIdBuf[0];

                    /** @var string $paramsJson */
                    $paramsJson  = $this->driver->getString($paramsBuf);
                    /** @var string $dtoDataJson */
                    $dtoDataJson = $this->driver->getString($dtoBuf);

                    $params  = Json::decode($paramsJson);
                    $dtoData = ($dtoDataJson !== 'null' && $dtoDataJson !== '')
                        ? Json::decode($dtoDataJson)
                        : null;

                    $request = new Request();
                    /** @var array<string, string> $params */
                    $request->setPathVars($params);

                    /** @var array{int, array<string>|(callable(): mixed), array<string, string>} $info */
                    $info = [1, $this->router->getRoutes()[(int)$handlerId][2], $params];
                    $routeMatch = new RouteMatch(
                        $info,
                        $this->router->getParamCache(),
                        [],
                        $this->router->getContainer(),
                        /** @var array<string, mixed> $dtoData */
                        $dtoData
                    );

                    $result = $routeMatch->execute($request);

                    $response = [
                        'status'  => 200,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'X-Powered-By' => 'Quill',
                        ],
                        'body' => (string)json_encode($result ?? []),
                    ];

                    $json = Json::encode($response);
                    /** @phpstan-ignore-next-line */
                    $this->driver->respond($id, $json);
                } catch (\Throwable $e) {
                    fwrite(STDERR, "[Worker " . getmypid() . "] Execution error: {$e->getMessage()}\n{$e->getTraceAsString()}\n");
                    $errJson = Json::encode([
                        'status' => 500,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => Json::encode(['error' => 'Internal Server Error', 'detail' => $e->getMessage()])
                    ]);
                    $this->driver->respond((int)$id, $errJson);
                }
            } else {
                // No pending request — yield CPU unless we are in bench mode.
                if (getenv('APP_ENV') !== 'bench') {
                    usleep(100);
                }
            }
        }
    }
}
