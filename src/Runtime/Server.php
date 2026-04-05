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

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    // ── Public entry-point ────────────────────────────────────────────────────

    public function start(int $port = 8080): void
    {
        $this->port = $port;
        $nWorkers   = max(1, (int) (getenv('QUILL_WORKERS') ?: 1));

        if ($nWorkers > 1 && function_exists('pcntl_fork')) {
            // Bind the TCP socket ONCE before any forks so every worker shares
            // the same kernel accept queue.  The kernel delivers each connection
            // to exactly one worker — guaranteed fair on macOS and Linux.
            /** @phpstan-ignore-next-line */
            $fd = Runtime::get()->quill_server_prebind($port);
            if ($fd < 0) {
                throw new \RuntimeException("Failed to pre-bind port {$port}. Is it already in use?");
            }
            $this->spawnWorkers($nWorkers);
        } else {
            $this->setupSignals([]);
            $this->bootWorker();
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
                // Reset signal handlers inherited from parent, then boot.
                $this->setupSignals([]);
                $this->bootWorker();
                $this->runEventLoop();
                exit(0);
            }

            $pids[] = $pid;
        }

        // ── Parent process ────────────────────────────────────────────────────
        $this->setupSignals($pids);
        $this->bootWorker();
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
        $ffi    = Runtime::get();
        $handle = $this->router->getHandle();

        if ($handle === null) {
            throw new \RuntimeException('Quill Router handle not initialized.');
        }

        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_server_listen($handle, $this->validator, $this->port);
        if ($res !== 0) {
            throw new \RuntimeException("Failed to listen on port {$this->port} (code: {$res})");
        }

        echo '[Worker ' . getmypid() . "] listening on http://0.0.0.0:{$this->port}\n";

        // Pre-allocate FFI buffers once — reused for every request.
        /** @var \FFI\CData $idBuf */
        $idBuf        = $ffi->new('uint32_t[1]');
        /** @var \FFI\CData $handlerIdBuf */
        $handlerIdBuf = $ffi->new('uint32_t[1]');
        /** @var \FFI\CData $paramsBuf */
        $paramsBuf    = $ffi->new('char[4096]');
        /** @var \FFI\CData $dtoBuf */
        $dtoBuf       = $ffi->new('char[65536]');

        while ($this->running) {
            /** @phpstan-ignore-next-line */
            $hasRequest = $ffi->quill_server_poll($idBuf, $handlerIdBuf, $paramsBuf, 4096, $dtoBuf, 65536);

            if ($hasRequest === 1) {
                try {
                    $id        = $idBuf[0];
                    $handlerId = $handlerIdBuf[0];

                    /** @var string $paramsJson */
                    $paramsJson  = \FFI::string($paramsBuf);
                    /** @var string $dtoDataJson */
                    $dtoDataJson = \FFI::string($dtoBuf);

                    $params  = Json::decode($paramsJson);
                    $dtoData = ($dtoDataJson !== 'null' && $dtoDataJson !== '')
                        ? Json::decode($dtoDataJson)
                        : null;

                    $request = new Request();
                    /** @var array<string, string> $params */
                    $request->setPathVars($params);

                    /** @var array{int, array<string>|(callable(): mixed), array<string, string>} $info */
                    $info = [1, $this->router->getRoutes()[$handlerId][2], $params];
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
                    $ffi->quill_server_respond($id, $json, strlen($json));
                } catch (\Throwable $e) {
                    $errJson = Json::encode(['status' => 500, 'body' => 'PHP Execution Error']);
                    /** @phpstan-ignore-next-line */
                    $ffi->quill_server_respond($id, $errJson, strlen($errJson));
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
