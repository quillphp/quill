<?php

declare(strict_types=1);

namespace Quill\Routing;

use Psr\Container\ContainerInterface;
use Quill\Http\Request;
use Quill\Validation\DTO;
use Quill\Validation\Validator;
use Quill\Runtime\Runtime;

/**
 * Handle-based Router for Quill.
 * High-performance binary routing engine.
 * Mandatory Quill Core requirement.
 */
class Router
{
    /** @var array<array{string, string, callable|array<string>}> */
    private array $routes = [];
    /** @var \FFI\CData|null Pointer to the native router instance */
    private ?\FFI\CData $handle = null;
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
     * Add a route for compilation into the core engine.
     *
     * @param callable|array<string> $handler
     */
    public function addRoute(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }

    /**
     * Compile the route manifest into the Quill Core.
     */
    public function compile(): void
    {
        if ($this->handle !== null) {
            return;
        }

        $paramCacheFile = $this->cacheFile ? $this->cacheFile . '.params' : null;

        if ($paramCacheFile && file_exists($paramCacheFile)) {
            $this->paramCache = (array)require $paramCacheFile;
        } else {
            $this->buildParamCache();
            if ($paramCacheFile) {
                file_put_contents($paramCacheFile, "<?php return " . var_export($this->paramCache, true) . ";");
            }
        }

        $ffi          = Runtime::get();
        $manifest     = $this->buildManifest();
        $manifestJson = (string)json_encode($manifest);
        
        /** @phpstan-ignore-next-line */
        $this->handle = $ffi->quill_router_build($manifestJson, strlen($manifestJson));
    }

    private function buildParamCache(): void
    {
        foreach ($this->routes as [$method, $path, $handler]) {
            if (is_array($handler) && count($handler) === 2 && isset($handler[0], $handler[1]) && is_scalar($handler[0]) && is_scalar($handler[1])) {
                $class = (string)$handler[0];
                $methodName = (string)$handler[1];
                $key = "$class::$methodName";
                if (!isset($this->paramCache[$key])) {
                    $reflection = new \ReflectionMethod($class, $methodName);
                    $this->paramCache[$key] = $this->reflectParams($reflection);
                }
            } elseif ($handler instanceof \Closure) {
                $key = 'closure_' . spl_object_id($handler);
                if (!isset($this->paramCache[$key])) {
                    $reflection = new \ReflectionFunction($handler);
                    $this->paramCache[$key] = $this->reflectParams($reflection);
                }
            }
        }
    }

    /**
     * @return array<int, array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>
     */
    private function reflectParams(\ReflectionFunctionAbstract $reflection): array
    {
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
        return $map;
    }

    private function getHandlerKey(mixed $handler): ?string
    {
        if (is_array($handler) && count($handler) === 2 && isset($handler[0], $handler[1]) && is_scalar($handler[0]) && is_scalar($handler[1])) {
            return "{$handler[0]}::{$handler[1]}";
        } elseif ($handler instanceof \Closure) {
            return 'closure_' . spl_object_id($handler);
        }
        return null;
    }

    /**
     * @return array<int, array{method: string, pattern: string, handler_id: int, dto_class: ?string}>
     */
    private function buildManifest(): array
    {
        $manifest = [];
        foreach ($this->routes as $index => [$method, $path, $handler]) {
            $dtoClass = null;
            $key = $this->getHandlerKey($handler);

            if ($key && isset($this->paramCache[$key])) {
                foreach ($this->paramCache[$key] as $param) {
                    if ($param['isDTO']) {
                        $dtoClass = $param['type'];
                        break;
                    }
                }
            }

            $manifest[] = [
                'method' => $method,
                'pattern' => $path,
                'handler_id' => $index,
                'dto_class' => $dtoClass,
            ];
        }
        return $manifest;
    }

    /** @return array<int, array{string, string, callable|array<string>}> */
    public function getRoutes(): array { return $this->routes; }
    /** @return array<string, array<int, array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> */
    public function getParamCache(): array { return $this->paramCache; }
    /** @return array<string, object> */
    public function getInstanceCache(): array { return $this->instanceCache; }
    public function getContainer(): ?ContainerInterface { return $this->container; }
    public function getHandle(): ?\FFI\CData { return $this->handle; }

    public function dispatch(string $method, string $path): RouteMatch
    {
        if ($this->handle === null) {
            $this->compile();
        }

        $res = $this->match($method, $path);

        if ($res['status'] === 1) { // Found
            /** @var array{int, callable|array<string>, array<string, string>} $info */
            $info = [1, $this->routes[$res['handler_id']][2], $res['params']];
            return new RouteMatch(
                $info,
                $this->paramCache,
                $this->instanceCache,
                $this->container
            );
        }

        if ($res['status'] === 2) { // Not Found
            return new RouteMatch([0, [], []], $this->paramCache, $this->instanceCache, $this->container);
        }

        if ($res['status'] === 3) { // Method Not Allowed
            return new RouteMatch([2, [], []], $this->paramCache, $this->instanceCache, $this->container);
        }

        return new RouteMatch([0, [], []], $this->paramCache, $this->instanceCache, $this->container);
    }

    /**
     * @return array{status: int, handler_id: int, params: array<string, string>}
     */
    private function match(string $method, string $path): array
    {
        $ffi          = Runtime::get();
        $handlerId    = $ffi->new('uint32_t[1]');
        $numParams    = $ffi->new('uint32_t[1]');
        $paramsJson   = $ffi->new('char[2048]');
        
        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_router_match(
            $this->handle,
            $method, strlen($method),
            $path, strlen($path),
            $handlerId,
            $numParams,
            $paramsJson, 2048
        );

        if ($res === 1) { // Not Found
            return ['status' => 2, 'handler_id' => 0, 'params' => []];
        }

        if ($res === 2) { // Method Not Allowed
            return ['status' => 3, 'handler_id' => 0, 'params' => []];
        }

        /** @var array<string, string> $params */
        /** @var \FFI\CData $paramsJson */
        $params = ($numParams instanceof \FFI\CData && (int)$numParams[0] > 0)
            ? json_decode(\FFI::string($paramsJson), true, 512, JSON_THROW_ON_ERROR)
            : [];

        return [
            'status'     => 1, // Found
            'handler_id' => $handlerId instanceof \FFI\CData ? (int)$handlerId[0] : 0,
            'params'     => $params,
        ];
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            $ffi = Runtime::get();
            /** @phpstan-ignore-next-line */
            $ffi->quill_router_free($this->handle);
            $this->handle = null;
        }
    }
}
