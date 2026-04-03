<?php

declare(strict_types=1);

namespace Quill;

/**
 * Quill — High-Performance PHP API Orchestrator
 */
class App
{
    /** @var array<array{string, string, callable|array<string>}> */
    private array $handlers = [];
    /** @var array<callable(Request, callable): mixed> */
    private array $middlewares = [];
    private ?Router $router = null;
    private Pipeline $pipeline;
    private bool $booted = false;
    /** @var array<string, string> */
    private array $groupStack = [];
    /** @var array<string, mixed> */
    private array $config = [];
    private ?Logger $logger = null;

    /**
     * @param array<string, mixed> $config
     *
     * Recognised config keys (all optional):
     *   'docs'        => bool          — enable /docs route
     *   'debug'       => bool          — verbose error responses
     *   'env'         => 'prod'|'dev'  — environment label
     *   'route_cache' => string|false  — cache file path or false to disable
     *   'logger'      => Logger|string — Logger instance, or a file path /
     *                                    'php://stderr' / 'php://stdout'
     *   'log_level'   => int           — minimum Logger::* level (default DEBUG)
     *   'log_format'  => 'text'|'json' — log format (default text)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'docs'       => false,
            'debug'      => false,
            'env'        => 'prod',
            'log_level'  => Logger::INFO,
            'log_format' => 'text',
        ], $config);

        // Bootstrap logger from config.
        if (isset($this->config['logger'])) {
            if ($this->config['logger'] instanceof Logger) {
                $this->logger = $this->config['logger'];
            } elseif (is_string($this->config['logger'])) {
                $this->logger = new Logger(
                    $this->config['logger'],
                    (int)$this->config['log_level'],
                    $this->config['log_format'] === 'json'
                );
            }
        }
    }

    /**
     * Replace (or disable) the logger at runtime.
     */
    public function setLogger(?Logger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Return the active logger (may be null if logging is disabled).
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    /**
     * Map a handler to multiple HTTP methods.
     * @param array<string> $methods
     * @param callable|array<string> $handler
     */
    public function map(array $methods, string $path, callable|array $handler): void
    {
        $prefix = implode('', $this->groupStack);
        foreach ($methods as $method) {
            $this->handlers[] = [strtoupper($method), $prefix . $path, $handler];
        }
    }

    /**
     * Register a route group with a common prefix.
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupStack[] = $prefix;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Map a full RESTful resource to a handler class.
     * Maps standard verbs to: index, store, show, update, destroy.
     */
    public function resource(string $path, string $handlerClass): void
    {
        $this->get($path, [$handlerClass, 'index']);
        $this->post($path, [$handlerClass, 'store']);
        $this->get("$path/{id}", [$handlerClass, 'show']);
        $this->put("$path/{id}", [$handlerClass, 'update']);
        $this->patch("$path/{id}", [$handlerClass, 'update']);
        $this->delete("$path/{id}", [$handlerClass, 'destroy']);
    }

    /**
     * Map a GET route.
     * @param callable|array<string> $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->map(['GET'], $path, $handler);
    }

    /**
     * Map a POST route.
     * @param callable|array<string> $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->map(['POST'], $path, $handler);
    }

    /**
     * Map a PUT route.
     * @param callable|array<string> $handler
     */
    public function put(string $path, callable|array $handler): void
    {
        $this->map(['PUT'], $path, $handler);
    }

    /**
     * Map a DELETE route.
     * @param callable|array<string> $handler
     */
    public function delete(string $path, callable|array $handler): void
    {
        $this->map(['DELETE'], $path, $handler);
    }

    /**
     * Map a PATCH route.
     * @param callable|array<string> $handler
     */
    public function patch(string $path, callable|array $handler): void
    {
        $this->map(['PATCH'], $path, $handler);
    }

    /**
     * Map a HEAD route (explicit handler override).
     * @param callable|array<string> $handler
     */
    public function head(string $path, callable|array $handler): void
    {
        $this->map(['HEAD'], $path, $handler);
    }

    /**
     * Map an OPTIONS route (explicit handler override).
     * @param callable|array<string> $handler
     */
    public function options(string $path, callable|array $handler): void
    {
        $this->map(['OPTIONS'], $path, $handler);
    }

    /**
     * Register a global middleware.
     * @param callable(Request, callable): mixed $middleware
     */
    public function use(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Perform the Boot Phase — runs ONCE per worker.
     * All reflection and compilation happens here.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // route_cache: false = disabled (worker mode / testing),
        //              string = custom path,
        //              not set = default temp-dir path (FPM mode).
        $cacheConfig = $this->config['route_cache'] ?? sys_get_temp_dir() . '/quill_routes.cache';
        $cacheFile   = ($cacheConfig === false) ? null : (string)$cacheConfig;

        $this->router = new Router(cacheFile: $cacheFile);
        $this->pipeline = new Pipeline();
 
        foreach ($this->handlers as [$method, $path, $handler]) {
            $this->router->addRoute($method, $path, $handler);
        }

        if ($this->config['docs'] ?? false) {
            $this->router->addRoute('GET', '/docs/openapi.json', function() {
                return (new OpenApi())->generate($this->handlers);
            });
            $this->router->addRoute('GET', '/docs', function() {
                $file = __DIR__ . '/../public/docs/index.html';
                if (!file_exists($file)) {
                    // Fallback for development if public/ is not in the same spot
                    $file = dirname(__DIR__) . '/public/docs/index.html';
                }
                if (!file_exists($file)) {
                    return new HttpResponse(['error' => 'Docs UI not found at ' . $file], 404);
                }
                return new HtmlResponse(file_get_contents($file));
            });
        }
 
        $this->router->compile();
        $this->booted = true;
    }

    /**
     * Handle a single request.
     * Accepts an optional Request for testing; defaults to the real HTTP request.
     *
     * ── Hot-path optimisation ──────────────────────────────────────────────────
     * When no middlewares are registered, the dispatch closure is never created.
     * This saves ~0.5 µs of object allocation per request in FrankenPHP worker
     * mode (where JIT is unavailable due to ZTS PHP).
     */
    public function handle(?Request $request = null): void
    {
        if (!$this->booted) {
            $this->boot();
        }

        $hasLogger = $this->logger !== null;
        $startTime = $hasLogger ? microtime(true) : 0.0;
        $request   = $request ?? new Request();
        $response  = new Response();
        $status    = 200;

        try {
            if (empty($this->middlewares)) {
                // ── Zero-allocation fast path: no closure, no Pipeline call ──────
                $method         = $request->method();
                $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;
                $route          = $this->router->dispatch($dispatchMethod, $request->path());

                if ($route->isFound()) {
                    if ($method === 'HEAD') {
                        $response->setHeadOnly(true);
                    }
                    $result = $route->execute($request);
                } elseif ($route->isMethodNotAllowed() && $method === 'OPTIONS') {
                    $allowed = array_merge($route->getAllowedMethods(), ['OPTIONS']);
                    if (PHP_SAPI !== 'cli' && !headers_sent()) {
                        http_response_code(204);
                        header('Allow: ' . implode(', ', $allowed));
                    }
                    $status = 204;
                    $result = [];
                } elseif ($route->isMethodNotAllowed()) {
                    $result = new HttpResponse(['status' => 405, 'error' => 'Method Not Allowed'], 405);
                } else {
                    $result = new HttpResponse(['status' => 404, 'error' => 'Not Found'], 404);
                }
            } else {
                // ── Middleware path ───────────────────────────────────────────────
                $dispatch = function ($req) use ($response, &$status): mixed {
                    $method         = $req->method();
                    $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;
                    $route          = $this->router->dispatch($dispatchMethod, $req->path());

                    if ($route->isFound()) {
                        if ($method === 'HEAD') {
                            $response->setHeadOnly(true);
                        }
                        return $route->execute($req);
                    }

                    if ($route->isMethodNotAllowed() && $method === 'OPTIONS') {
                        $allowed = array_merge($route->getAllowedMethods(), ['OPTIONS']);
                        if (PHP_SAPI !== 'cli' && !headers_sent()) {
                            http_response_code(204);
                            header('Allow: ' . implode(', ', $allowed));
                        }
                        $status = 204;
                        return [];
                    }

                    if ($route->isMethodNotAllowed()) {
                        return new HttpResponse(['status' => 405, 'error' => 'Method Not Allowed'], 405);
                    }

                    return new HttpResponse(['status' => 404, 'error' => 'Not Found'], 404);
                };

                $result = $this->pipeline->send($this->middlewares)->then($request, $dispatch);
            }

            $this->sendResponse($result, $response);
            $status = $response->getStatus();
        } catch (\Throwable $e) {
            $this->handleException($e, $response);
            $status = $response->getStatus();
        }

        // Access log — zero overhead when logger is null.
        if ($hasLogger) {
            $this->logger->access(
                ip:         $request->ip(),
                method:     $request->method(),
                path:       $request->path(),
                protocol:   $request->protocol(),
                status:     $status,
                bytes:      $response->getBytesSent(),
                referer:    $request->header('Referer', '-') ?? '-',
                userAgent:  $request->header('User-Agent', '-') ?? '-',
                durationMs: (microtime(true) - $startTime) * 1000.0
            );
        }
    }

    /**
     * Send the result back to the client using the Response strategy.
     */
    private function sendResponse(mixed $result, Response $response): void
    {
        if ($result instanceof HtmlResponse) {
            foreach ($result->headers as $name => $value) {
                $response->header($name, $value);
            }
            $response->html($result->html, $result->status);
            return;
        }

        if ($result instanceof HttpResponse) {
            foreach ($result->headers as $name => $value) {
                $response->header($name, $value);
            }
            if ($result->status === 204) {
                $response->setHeadOnly(true);
            }
            $response->json($result->data, $result->status);
            return;
        }

        // Hot path: non-empty result → always 200.
        if (!empty($result)) {
            $response->json($result);
            return;
        }

        $currentCode = http_response_code();
        if ($currentCode === 204) {
            $response->setHeadOnly(true);
            $response->json($result, 204);
        } else {
            $response->json($result);
        }
    }

    /**
     * Standard error handling based on ENV.
     */
    private function handleException(\Throwable $e, Response $response): void
    {
        if ($e instanceof ValidationException) {
            $response->json([
                'status' => 422,
                'error'  => 'Validation Failed',
                'errors' => $e->getErrors(),
            ], 422);
            return;
        }

        $status = ($e instanceof \InvalidArgumentException) ? 400 : 500;
        
        if ($this->config['debug'] || $this->config['env'] === 'dev') {
            $response->json([
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ], $status);
        } else {
            $response->json([
                'status' => $status,
                'error' => $status === 400 ? $e->getMessage() : 'Internal Server Error',
                'status_code' => $status,
            ], $status);
        }
    }

    /**
     * @return array<array{string, string, callable|array<string>}>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Run the application loop.
     * Supports FrankenPHP, RoadRunner, and Swoole natively.
     */
    public function run(): void
    {
        $this->boot();

        // ── Swoole HTTP server ────────────────────────────────────────────────
        if (PHP_SAPI === 'cli' && class_exists(\Swoole\Http\Server::class)) {
            $this->runWithSwoole();
            return;
        }

        // ── FrankenPHP worker loop ────────────────────────────────────────────
        if (function_exists('frankenphp_handle_request') && ($_SERVER['FRANKENPHP_WORKER'] ?? false)) {
            $gcInterval = (int)(getenv('QUILL_GC_INTERVAL') ?: 500);
            $gcTick = 0;
            while (frankenphp_handle_request(function () {
                $this->handle();
            })) {
                if ($gcInterval > 0 && ++$gcTick >= $gcInterval) {
                    gc_collect_cycles();
                    $gcTick = 0;
                }
            }
            return;
        }

        // ── RoadRunner ───────────────────────────────────────────────────────
        if (class_exists('Spiral\RoadRunner\Worker')) {
            $worker = \Spiral\RoadRunner\Worker::create();
            while ($worker->waitPayload()) {
                $this->handle();
                // @phpstan-ignore class.notFound
                $worker->respond(new \Spiral\RoadRunner\Payload(''));
            }
            return;
        }

        // ── Standard FPM / CLI fallback ──────────────────────────────────────
        $this->handle();
    }

    /**
     * Swoole HTTP server run loop.
     *
     * Key differences from the previous implementation:
     *  • Uses handleSwooleRequest() which writes directly to the Swoole response
     *    object — no ob_start / ob_get_clean / headers_list / header_remove.
     *  • Supports SWOOLE_BASE mode (set SWOOLE_MODE=base) for Linux deployments
     *    where each worker accepts connections independently (no master dispatch).
     *  • Larger socket backlog + buffer sizes to prevent TCP backpressure at
     *    high connection counts.
     *
     * Environment variables:
     *   SWOOLE_WORKERS     number of worker processes (default: CPU count)
     *   SWOOLE_PORT        listening port            (default: 8080)
     *   SWOOLE_MODE        'base' or 'process'       (default: process)
     *   QUILL_GC_INTERVAL  same as FrankenPHP mode   (default: 500, 0 = off)
     */
    private function runWithSwoole(): void
    {
        $workers    = (int)(getenv('SWOOLE_WORKERS') ?: (function_exists('swoole_cpu_num') ? swoole_cpu_num() : 4));
        $port       = (int)(getenv('SWOOLE_PORT') ?: 8080);
        $gcInterval = (int)(getenv('QUILL_GC_INTERVAL') ?: 500);
        // SWOOLE_BASE: each worker accepts connections directly (no master-process
        // dispatch pipe). Faster on Linux with SO_REUSEPORT. Use SWOOLE_PROCESS
        // (default) on macOS/Docker where REUSEPORT behaviour differs.
        // Constants are defined by the Swoole extension (0 = PROCESS, 1 = BASE).
        $swooleProcess = defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0;
        $swooleBase    = defined('SWOOLE_BASE')    ? SWOOLE_BASE    : 1;
        $mode = (getenv('SWOOLE_MODE') === 'base') ? $swooleBase : $swooleProcess;

        $server = new \Swoole\Http\Server('0.0.0.0', $port, $mode);
        $server->set([
            'worker_num'               => $workers,
            // 0 = no worker recycling. Safe for benchmarks (GC throttle handles
            // memory; set to 100000 in production to cap memory growth).
            'max_request'              => (int)(getenv('SWOOLE_MAX_REQUEST') ?: 0),
            'http_compression'         => false,    // disable gzip — raw throughput only
            'open_http2_protocol'      => false,
            'backlog'                  => 8192,     // larger TCP listen queue
            'max_conn'                 => 100000,
            'heartbeat_check_interval' => -1,       // disable keep-alive pings
            'buffer_output_size'       => 4 * 1024 * 1024,  // 4 MB per-connection output buffer
            'socket_buffer_size'       => 8 * 1024 * 1024,  // 8 MB kernel socket buffer
            'enable_coroutine'         => false,    // pure sync — no coroutine overhead
        ]);

        $server->on('start', static function () use ($workers, $port, $mode, $swooleBase): void {
            $modeName = ($mode === $swooleBase) ? 'BASE' : 'PROCESS';
            echo "Quill Swoole server ({$modeName}): {$workers} workers on 0.0.0.0:{$port}\n";
        });

        $app    = $this;
        $gcTick = 0;

        $server->on('request', static function (
            \Swoole\Http\Request  $swReq,
            \Swoole\Http\Response $swRes
        ) use ($app, $gcInterval, &$gcTick): void {
            $app->handleSwooleRequest($swReq, $swRes);

            if ($gcInterval > 0 && ++$gcTick >= $gcInterval) {
                gc_collect_cycles();
                $gcTick = 0;
            }
        });

        $server->start();
    }

    /**
     * Zero-copy Swoole request handler.
     *
     * Eliminates the ob_start / ob_get_clean / headers_list / header_remove
     * bridge used by the previous implementation, saving ~2–3 µs per request:
     *
     *   Old path: ob_start → handle() → [echo, header()] → ob_get_clean
     *             → headers_list → [explode+trim] × N → header_remove → end()
     *
     *   New path: dispatch → execute → sendSwooleResponse → end()
     *
     * The no-middleware hot path never allocates a closure, matching the
     * handle() optimisation.
     *
     * Middleware compatibility note:
     *   Middleware that calls header() / http_response_code() directly (e.g.
     *   Cors::middleware()) is bridged via headers_list() ONLY in the middleware
     *   path.  The no-middleware hot path is always fully zero-copy.
     */
    private function handleSwooleRequest(
        \Swoole\Http\Request  $swReq,
        \Swoole\Http\Response $swRes
    ): void {
        // Minimal superglobal setup — only the keys Request reads for dispatch.
        $serverInfo = $swReq->server ?? [];
        $_SERVER['REQUEST_METHOD']  = strtoupper($serverInfo['request_method'] ?? 'GET');
        $_SERVER['REQUEST_URI']     = $serverInfo['request_uri'] ?? '/';
        $_SERVER['REMOTE_ADDR']     = $serverInfo['remote_addr'] ?? '127.0.0.1';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_GET = $swReq->get ?? [];

        $method         = $_SERVER['REQUEST_METHOD'];
        $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;

        // Build Request with lazy Swoole headers — avoids populating all
        // $_SERVER['HTTP_*'] keys for routes that never call $req->header().
        $phpReq = new Request();
        if (!empty($swReq->header)) {
            $phpReq = $phpReq->withSwooleHeaders($swReq->header);
        }
        if ($raw = $swReq->rawContent()) {
            $phpReq = $phpReq->withInput($raw);
        }

        try {
            if (empty($this->middlewares)) {
                // ── Zero-allocation hot path ─────────────────────────────────
                $route = $this->router->dispatch($dispatchMethod, $phpReq->path());

                if (!$route->isFound()) {
                    if ($route->isMethodNotAllowed() && $method === 'OPTIONS') {
                        $allowed = array_merge($route->getAllowedMethods(), ['OPTIONS']);
                        $swRes->status(204);
                        $swRes->header('Allow', implode(', ', $allowed));
                        $swRes->end('');
                        return;
                    }
                    $swRes->status($route->isMethodNotAllowed() ? 405 : 404);
                    $swRes->header('Content-Type', 'application/json');
                    $swRes->end($route->isMethodNotAllowed()
                        ? '{"status":405,"error":"Method Not Allowed"}'
                        : '{"status":404,"error":"Not Found"}');
                    return;
                }

                $result = $route->execute($phpReq);

                if ($method === 'HEAD') {
                    $swRes->status(200);
                    $swRes->header('Content-Type', 'application/json');
                    $swRes->end('');
                    return;
                }

                $this->sendSwooleResponse($result, $swRes);

            } else {
                // ── Middleware path: bridge header() calls for compatibility ──
                // (Not on the benchmark hot path; no-middleware is the common case.)
                $dispatch = function (Request $req) use ($method): mixed {
                    $dispatchMethod = ($method === 'HEAD') ? 'GET' : $method;
                    $route = $this->router->dispatch($dispatchMethod, $req->path());

                    if ($route->isFound()) {
                        return $route->execute($req);
                    }
                    if ($route->isMethodNotAllowed()) {
                        return new HttpResponse(['status' => 405, 'error' => 'Method Not Allowed'], 405);
                    }
                    return new HttpResponse(['status' => 404, 'error' => 'Not Found'], 404);
                };

                $result = $this->pipeline->send($this->middlewares)->then($phpReq, $dispatch);

                if ($method === 'HEAD') {
                    $swRes->status(200);
                    $swRes->header('Content-Type', 'application/json');
                    // Bridge any headers set by middleware via header().
                    foreach (headers_list() as $line) {
                        [$n, $v] = explode(':', $line, 2);
                        $swRes->header(trim($n), trim($v));
                    }
                    header_remove();
                    $swRes->end('');
                    return;
                }

                // Flush headers set by middleware (e.g. CORS) to Swoole response.
                $phpHeaders = headers_list();
                if ($phpHeaders) {
                    foreach ($phpHeaders as $line) {
                        [$n, $v] = explode(':', $line, 2);
                        $swRes->header(trim($n), trim($v));
                    }
                    header_remove();
                }

                $this->sendSwooleResponse($result, $swRes);
            }
        } catch (ValidationException $e) {
            $swRes->status(422);
            $swRes->header('Content-Type', 'application/json');
            $swRes->end(json_encode([
                'status' => 422,
                'error'  => 'Validation Failed',
                'errors' => $e->getErrors(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            $status = ($e instanceof \InvalidArgumentException) ? 400 : 500;
            $swRes->status($status);
            $swRes->header('Content-Type', 'application/json');
            if ($this->config['debug'] || $this->config['env'] === 'dev') {
                $swRes->end(json_encode([
                    'status' => $status,
                    'error'  => $e->getMessage(),
                    'trace'  => explode("\n", $e->getTraceAsString()),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $swRes->end($status === 400
                    ? json_encode(['status' => 400, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : '{"status":500,"error":"Internal Server Error"}');
            }
        }
    }

    /**
     * Write a route handler result directly to a Swoole response object.
     * Zero overhead: no echo(), no header(), no output buffering.
     *
     * Mirrors sendResponse() but operates on \Swoole\Http\Response directly,
     * using the fast JSON flags on every encode call.
     */
    private function sendSwooleResponse(mixed $result, \Swoole\Http\Response $swRes): void
    {
        if ($result instanceof HtmlResponse) {
            $swRes->status($result->status);
            $swRes->header('Content-Type', 'text/html');
            foreach ($result->headers as $k => $v) {
                $swRes->header($k, $v);
            }
            $swRes->end($result->html);
            return;
        }

        if ($result instanceof HttpResponse) {
            $swRes->status($result->status);
            $swRes->header('Content-Type', 'application/json');
            foreach ($result->headers as $k => $v) {
                $swRes->header($k, $v);
            }
            if ($result->status !== 204) {
                $swRes->end(json_encode(
                    $result->data,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ));
            } else {
                $swRes->end('');
            }
            return;
        }

        // ── Hot path: plain array / scalar — always HTTP 200 ─────────────────
        $swRes->status(200);
        $swRes->header('Content-Type', 'application/json');
        $swRes->end(json_encode(
            $result,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
