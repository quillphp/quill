<?php

declare(strict_types=1);

namespace Quill;

use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;

/**
 * Encapsulates the result of a route dispatch.
 */
class RouteMatch
{
    private int $status;
    /** @var callable|array<string>|null */
    private mixed $handler;
    /** @var array<string, string> */
    private array $vars;
    /** @var array<string, array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> */
    private array $paramCache;
    /** @var array<string, object> */
    private array $instanceCache;
    private ?ContainerInterface $container;

    /**
     * @param array{0: int, 1?: callable|array<string>, 2?: array<string, string>} $info
     * @param array<string, array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> $paramCache
     * @param array<string, object> $instanceCache
     */
    public function __construct(
        array $info,
        array $paramCache,
        array &$instanceCache,
        ?ContainerInterface $container = null
    ) {
        $this->status        = $info[0];
        $this->handler       = $info[1] ?? null;
        $this->vars          = $info[2] ?? [];
        $this->paramCache    = $paramCache;
        $this->instanceCache = &$instanceCache;
        $this->container     = $container;
    }

    public function isFound(): bool
    {
        return $this->status === Dispatcher::FOUND;
    }

    public function isNotFound(): bool
    {
        return $this->status === Dispatcher::NOT_FOUND;
    }

    public function isMethodNotAllowed(): bool
    {
        return $this->status === Dispatcher::METHOD_NOT_ALLOWED;
    }

    /**
     * Returns the HTTP methods allowed for this path (only set on METHOD_NOT_ALLOWED).
     *
     * @return array<string>
     */
    public function getAllowedMethods(): array
    {
        if ($this->status === Dispatcher::METHOD_NOT_ALLOWED) {
            return (array)($this->handler ?? []);
        }
        return [];
    }

    public function execute(Request $request): mixed
    {
        $request->setPathVars($this->vars);

        if (is_array($this->handler) && count($this->handler) === 2) {
            [$class, $method] = $this->handler;
            $key = "$class::$method";

            // Array handlers: use boot-time cached param map — zero reflection per request.
            $paramMap = $this->paramCache[$key] ?? [];

            // Fast path: zero-arg handler — skip resolveArgs entirely.
            $args = $paramMap ? $this->resolveArgs($paramMap, $request) : [];

            if (!isset($this->instanceCache[$class])) {
                $this->instanceCache[$class] = $this->container?->has($class)
                    ? $this->container->get($class)
                    : new $class();
            }
            return $this->instanceCache[$class]->$method(...$args);
        }

        if (is_callable($this->handler)) {
            // Closure handlers: use boot-time cached param map keyed by object id — zero reflection per request.
            $handler = $this->handler;
            $key = is_object($handler) 
                ? 'closure_' . spl_object_id($handler)
                : 'func_' . (string)$handler;

            // Fast path: zero-arg handler — skip resolveArgs entirely.
            if (isset($this->paramCache[$key])) {
                $paramMap = $this->paramCache[$key];
                $args = $paramMap ? $this->resolveArgs($paramMap, $request) : [];
            } else {
                // Should only happen on first call when cache is cold (non-worker mode).
                $paramMap = $this->buildParamMap(
                    (new \ReflectionFunction(\Closure::fromCallable($this->handler)))->getParameters()
                );
                $args = $paramMap ? $this->resolveArgs($paramMap, $request) : [];
            }

            return ($this->handler)(...$args);
        }

        throw new \RuntimeException('Invalid route handler.');
    }

    /**
     * Build a normalised param map from an array of ReflectionParameter objects.
     * Used for both array handlers (at boot) and closure handlers (per-request).
     *
     * @param array<\ReflectionParameter> $params
     * @return array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>
     */
    private function buildParamMap(array $params): array
    {
        $map = [];
        foreach ($params as $param) {
            $type = $param->getType();
            $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : null;
            $isDTO = false;
            $isReq = false;
            if ($typeName && !$type->isBuiltin()) {
                $isDTO = is_subclass_of($typeName, DTO::class);
                $isReq = ($typeName === Request::class);
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
        return $map;
    }

    /**
     * Resolve a param map against the current request and path vars into an args array.
     *
     * @param array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}> $paramMap
     * @return array<mixed>
     */
    private function resolveArgs(array $paramMap, Request $request): array
    {
        $args = [];
        foreach ($paramMap as $param) {
            $name = $param['name'];
            if ($param['isDTO'] && $param['type'] !== null) {
                $args[] = Validator::validate($param['type'], $request->json());
                continue;
            }
            if ($param['isRequest']) {
                $args[] = $request;
                continue;
            }
            if (isset($this->vars[$name])) {
                $args[] = match ($param['type']) {
                    'int'   => (int)$this->vars[$name],
                    'float' => (float)$this->vars[$name],
                    'bool'  => (bool)$this->vars[$name],
                    default => $this->vars[$name],
                };
                continue;
            }

            // Fallback to Container for non-builtin types
            if ($param['type'] !== null && !$param['isBuiltin'] && $this->container?->has($param['type'])) {
                $args[] = $this->container->get($param['type']);
                continue;
            }

            $args[] = $param['hasDefault'] ? $param['defaultValue'] : null;
        }
        return $args;
    }
}
