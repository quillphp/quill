<?php

declare(strict_types=1);

namespace Quill;
 
 use Psr\Container\ContainerInterface;
 use Quill\Contracts\PluginInterface;

/**
 * Quill — High-Performance PHP API Orchestrator
 */
class App
{
    public const string VERSION = '0.0.1';

    use Concerns\HandlesRouting;
    use Concerns\HandlesSwoole;
    use Concerns\HandlesExceptions;
    use Concerns\HandlesResponses;

    /** @var array<callable|class-string> */
    private array $middlewares = [];
    /** @var array<PluginInterface> */
    private array $plugins = [];
    private ?Router $router = null;
    private Pipeline $pipeline;
    private bool $booted = false;
    /** @var array<string, mixed> */
    private array $config = [];
    private ?Logger $logger = null;
    private ?ContainerInterface $container = null;

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
                    is_int($this->config['log_level']) ? (int)$this->config['log_level'] : 0,
                    $this->config['log_format'] === 'json'
                );
            }
        }

        // Initialize core components
        $cacheConfig = $this->config['route_cache'] ?? sys_get_temp_dir() . '/quill_routes.cache';
        $cacheFile   = is_string($cacheConfig) ? $cacheConfig : null;
        $this->router = new Router(cacheFile: $cacheFile);
        $this->pipeline = new Pipeline();
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
     * Set a PSR-11 compliant container for dependency injection.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * PSR-11: Check if an entry exists in the container.
     */
    public function has(string $id): bool
    {
        return $this->container?->has($id) ?? false;
    }

    /**
     * Set an entry in the container (if the internal container supports it).
     */
    public function set(string $id, mixed $value): void
    {
        if ($this->container && method_exists($this->container, 'set')) {
            $this->container->set($id, $value);
        }
    }

    /**
     * Get the underlying PSR-11 container.
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }


    /**
     * Register a plugin.
     */
    public function register(PluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
        $plugin->register($this);
    }

    /**
     * Register a global middleware.
     * @param (callable(Request, callable): mixed)|class-string $middleware
     */
    public function use(callable|string $middleware): void
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

        if ($this->container && $this->router !== null) {
            $this->router->setContainer($this->container);
            $this->pipeline->setContainer($this->container);
        }
 
        foreach ($this->handlers as [$method, $path, $handler]) {
            if ($this->router !== null) {
                $this->router->addRoute($method, $path, $handler);
            }
        }

        if (($this->config['docs'] ?? false) && $this->router !== null) {
            $this->router->addRoute('GET', '/docs/openapi.json', function() {
                return (new OpenApi())->generate($this->handlers);
            });
        }
 
        if ($this->router !== null) {
            $this->router->compile();
        }

        foreach ($this->plugins as $plugin) {
            $plugin->boot($this);
        }

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
                $router         = $this->router;
                if ($router === null) { throw new \RuntimeException('Router not initialized.'); }
                $route          = $router->dispatch($dispatchMethod, $request->path());

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
                    $router         = $this->router;
                    if ($router === null) { throw new \RuntimeException('Router not initialized.'); }
                    $route          = $router->dispatch($dispatchMethod, $req->path());

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
        if ($hasLogger && $this->logger !== null) {
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

}
