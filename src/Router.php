<?php

declare(strict_types=1);

namespace Quill;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;
use function FastRoute\cachedDispatcher;

/**
 * FastRoute wrapper for Quill.
 * Compiled once, used forever.
 */
class Router
{
    /** @var array<array{string, string, callable|array<string>}> */
    private array $routes = [];
    private ?Dispatcher $dispatcher = null;
    /** @var array<string, array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> */
    private array $paramCache = [];
    /** @var array<string, object> */
    private array $instanceCache = [];
    private ?ContainerInterface $container = null;

    public function __construct(
        private ?string $cacheFile = null
    ) {
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Add a route to the internal list for compilation.
     *
     * @param callable|array<string> $handler
     */
    public function addRoute(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }

    public function compile(): void
    {
        $paramCacheFile = $this->cacheFile ? $this->cacheFile . '.params' : null;

        // If we have a warm cache for both FastRoute and our internal metadata, load them and exit.
        if ($paramCacheFile && file_exists($paramCacheFile) && file_exists($this->cacheFile)) {
            $this->paramCache = require $paramCacheFile;
            $this->dispatcher = cachedDispatcher(fn() => null, [
                'cacheFile'     => $this->cacheFile,
                'cacheDisabled' => false,
            ]);
            return;
        }

        // Cache miss or disabled: Build the param cache via reflection.
        foreach ($this->routes as [$method, $path, $handler]) {
            if (is_array($handler) && count($handler) === 2) {
                $class = is_string($handler[0] ?? null) ? (string)$handler[0] : '';
                $methodName = is_string($handler[1] ?? null) ? (string)$handler[1] : '';
                $key = "$class::$methodName";
                if (!isset($this->paramCache[$key])) {
                    $reflection = new \ReflectionMethod($class, $methodName);
                    $map = [];
                    foreach ($reflection->getParameters() as $param) {
                        $type = $param->getType();
                        $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : null;
                        $isDTO = false;
                        if ($typeName && !$type->isBuiltin()) {
                            if (is_subclass_of($typeName, DTO::class)) {
                                $isDTO = true;
                                /** @var class-string<DTO> $typeName */
                                Validator::register($typeName);
                            }
                        }
                        $map[] = [
                            'name'         => $param->getName(),
                            'type'         => $typeName,
                            'isBuiltin'    => $type instanceof \ReflectionNamedType ? $type->isBuiltin() : true,
                            'isDTO'        => $isDTO,
                            'isRequest'    => $typeName === Request::class,
                            'hasDefault'   => $param->isDefaultValueAvailable(),
                            'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                        ];
                    }
                    $this->paramCache[$key] = $map;
                }
            } elseif ($handler instanceof \Closure) {
                // Pre-compile closure param maps at boot — zero reflection on the hot path.
                // Keyed by spl_object_id (stable for the lifetime of the worker/process).
                $key = 'closure_' . spl_object_id($handler);
                if (!isset($this->paramCache[$key])) {
                    $reflection = new \ReflectionFunction($handler);
                    $map = [];
                    foreach ($reflection->getParameters() as $param) {
                        $type = $param->getType();
                        $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : null;
                        $isDTO = false;
                        $isReq = false;
                        if ($typeName && !$type->isBuiltin()) {
                            $isDTO = is_subclass_of($typeName, DTO::class);
                            $isReq = ($typeName === Request::class);
                            if ($isDTO) {
                                /** @var class-string $typeName */
                                Validator::register($typeName);
                            }
                        }
                        $map[] = [
                            'name'         => $param->getName(),
                            'type'         => $typeName,
                            'isBuiltin'    => $type instanceof \ReflectionNamedType ? $type->isBuiltin() : true,
                            'isDTO'        => $isDTO,
                            'isRequest'    => $isReq,
                            'hasDefault'   => $param->isDefaultValueAvailable(),
                            'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                        ];
                    }
                    $this->paramCache[$key] = $map;
                }
            }
        }

        // FastRoute dispatcher — uses disk cache in FPM mode, in-memory only when null.
        $this->dispatcher = cachedDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        }, [
            'cacheFile'     => $this->cacheFile ?? '',
            'cacheDisabled' => ($this->cacheFile === null),
        ]);

        // Save the internal param metadata cache.
        if ($paramCacheFile) {
            file_put_contents($paramCacheFile, "<?php return " . var_export($this->paramCache, true) . ";");
        }
    }

    /**
     * High-speed dispatching.
     */
    public function dispatch(string $method, string $uri): RouteMatch
    {
        if ($this->dispatcher === null) {
            $this->compile();
        }

        if ($this->dispatcher === null) {
            throw new \RuntimeException('Dispatcher failed to compile.');
        }

        /** @var array{0: int, 1?: callable|array<string>, 2?: array<string, string>} $info */
        $info = $this->dispatcher->dispatch($method, $uri);

        return new RouteMatch($info, $this->paramCache, $this->instanceCache, $this->container);
    }

    /**
     * Clear the compiled route cache.
     */
    public function clearCache(): void
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $paramCacheFile = $this->cacheFile ? $this->cacheFile . '.params' : null;
        if ($paramCacheFile && file_exists($paramCacheFile)) {
            unlink($paramCacheFile);
        }
    }
}

