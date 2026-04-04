<?php

declare(strict_types=1);

namespace Quill;

use Psr\Container\ContainerInterface;
use Quill\Http\Request;
use Quill\Routing\Router;
use Quill\Runtime\Runtime;
use Quill\Runtime\Server;
use Quill\Concerns\HandlesExceptions;

/**
 * The Quill Application Kernel.
 * High-performance, binary-first core.
 * Mandatory Quill Runtime requirement.
 */
class App
{
    use HandlesExceptions;

    protected Router $router;
    protected ?ContainerInterface $container = null;
    protected array $config = [];
    protected array $middlewares = [];
    protected ?Pipeline $pipeline = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'env'   => 'prod',
            'debug' => false,
            'root'  => getcwd(),
        ], $config);

        $this->router = new Router($config['cache_file'] ?? null);
        $this->pipeline = new Pipeline();
    }

    public function boot(): void
    {
        // Initialize the mandatory binary runtime (automatic discovery)
        Runtime::boot();

        if (!Runtime::isAvailable()) {
            throw new \RuntimeException("Quill Core (libquill) not found. Please ensure the native engine is installed.");
        }
    }

    /**
     * Run the application loop.
     * Supports Runtime (Native), Swoole, and Worker modes.
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
        $server = new Server($this->router);
        $server->start($port);
    }

    protected function handleRequest(): void
    {
        try {
            $request = new Request();
            $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            
            $route   = $this->router->dispatch($method, (string)$path);
            $result  = $route->execute($request);

            if (is_string($result)) {
                echo $result;
            } else {
                echo json_encode($result);
            }
        } catch (\Throwable $e) {
            $this->renderException($e);
        }
    }

    public function getRouter(): Router { return $this->router; }
}
