<?php

declare(strict_types=1);

namespace Quill;

use Psr\Container\ContainerInterface;
use Quill\Http\Request;
use Quill\Http\Response;
use Quill\Routing\Router;
use Quill\Runtime\Runtime;
use Quill\Runtime\Server;
use Quill\Concerns\HandlesExceptions;
use Quill\Concerns\HandlesRouting;
use Quill\Concerns\HandlesResponses;
use Quill\Container\Container;

/**
 * The Quill Application Kernel.
 * High-performance, binary-first core.
 * Mandatory Quill Runtime requirement.
 */
class App
{
    use HandlesExceptions, HandlesRouting, HandlesResponses;

    protected Router $router;
    protected ?ContainerInterface $container = null;
    /** @var array<string, mixed> */
    protected array $config = [];
    /** @var array<int, callable|string|object> */
    protected array $middlewares = [];
    protected Pipeline $pipeline;
    protected bool $booted = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        // Load environment variables from .env if present
        \Quill\Support\Env::load(getcwd() . '/.env');

        $this->config = array_merge([
            'env'   => \Quill\Support\Env::get('APP_ENV', 'prod'),
            'debug' => \Quill\Support\Env::get('APP_DEBUG', false),
            'root'  => getcwd(),
        ], $config);

        $cacheFile = (isset($config['cache_file']) && is_scalar($config['cache_file'])) ? (string)$config['cache_file'] : null;
        $this->router = new Router($cacheFile);
        $this->pipeline = new Pipeline();

        if ($this->container === null) {
            $this->setContainer(new Container());
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Initialize the mandatory binary runtime (automatic discovery)
        Runtime::boot();

        if (!Runtime::isAvailable()) {
            throw new \RuntimeException("Quill Core (libquill) not found. Please ensure the native engine is installed.");
        }

        // Routes registration
        foreach ($this->getHandlers() as [$method, $path, $handler]) {
            $this->router->addRoute($method, $path, $handler);
        }

        if (PHP_SAPI === 'cli') {
            $this->router->compile();
        }

        $this->booted = true;
    }

    /**
     * Entry point for manual request handling (useful for testing).
     */
    public function handle(?Request $request = null): void
    {
        $this->boot();
        $this->handleRequest($request);
    }

    /**
     * Start the Quill application lifecycle.
     * Serves the application via the high-performance Binary Core.
     */
    public function run(): void
    {
        $this->boot();

        // ── Quill Runtime (Ultra High Performance) ───────────────────────────
        if (PHP_SAPI === 'cli' && Runtime::isStarted()) {
            $this->runWithQuill();
            return;
        }

        // FPM / CGI Fallback (Legacy)
        $this->handleRequest();
    }

    private function runWithQuill(): void
    {
        $port = (int)(getenv('QUILL_PORT') ?: 8080);
        
        $dtoBufferSize = (isset($this->config['ffi_dto_buffer_size']) && is_numeric($this->config['ffi_dto_buffer_size'])) 
            ? (int)$this->config['ffi_dto_buffer_size'] 
            : 65536;
        $errorBufferSize = (isset($this->config['ffi_error_buffer_size']) && is_numeric($this->config['ffi_error_buffer_size'])) 
            ? (int)$this->config['ffi_error_buffer_size'] 
            : 4096;

        $logger = $this->config['logger'] ?? null;
        if ($logger === null) {
            $cliLogger = new \Quill\Logger('php://stdout');
            $logPath = getenv('QUILL_LOG') ?: ($this->config['log_file'] ?? null);
            
            if ($logPath === 'true' || $logPath === '1') {
                $logPath = (string)getcwd() . '/storage/logs/quill.log';
            }
            
            if (is_string($logPath) && $logPath !== '' && $logPath !== 'false' && $logPath !== '0') {
                $fileLogger = new \Quill\Logger($logPath);
                $logger = new \Quill\Runtime\MultiLogger([$cliLogger, $fileLogger]);
                // Pass log file to native engine for fast-path logging
                Runtime::getDriver()->setLogFile($logPath);
            } else {
                $logger = $cliLogger;
            }
        }

        /** @var \Psr\Log\LoggerInterface|null $logger */
        $server = new Server($this->router, null, $logger, $dtoBufferSize, $errorBufferSize);
        $server->start($port);
    }

    public function use(callable|string|object $middleware): static
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;
        $this->router->setContainer($container);
        $this->pipeline->setContainer($container);
        return $this;
    }

    public function singleton(string $id, mixed $concrete = null): static
    {
        if ($this->container instanceof Container) {
            $this->container->singleton($id, $concrete);
        }
        return $this;
    }

    public function bind(string $id, mixed $concrete = null, bool $singleton = false): static
    {
        if ($this->container instanceof Container) {
            $this->container->bind($id, $concrete, $singleton);
        }
        return $this;
    }

    protected function handleRequest(?Request $request = null): void
    {
        try {
            $request = $request ?? new Request();
            
            $result = $this->pipeline
                /** @phpstan-ignore-next-line */
                ->send($this->middlewares)
                ->then($request, function ($request) {
                    $method  = $request->method();
                    $path    = $request->path();
                    $route   = $this->router->dispatch($method, (string)$path);

                    if (!$route->isFound()) {
                        $status = $route->isMethodNotAllowed() ? 405 : 404;
                        $error  = $route->isMethodNotAllowed() ? 'Method Not Allowed' : 'Not Found';
                        return ['status' => $status, 'error' => $error];
                    }

                    return $route->execute($request);
                });

            $this->sendResponse($result, new Response());
        } catch (\Throwable $e) {
            $response = new Response();
            $this->handleException($e, $response);
        }
    }

    public function has(string $id): bool
    {
        return $this->container !== null && $this->container->has($id);
    }

    public function getRouter(): Router { return $this->router; }
}
