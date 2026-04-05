<?php

declare(strict_types=1);

namespace Quill\Routing;

use Quill\Http\Request;
use Psr\Container\ContainerInterface;
use Quill\Validation\DTO;
use Quill\Validation\Validator;
use Quill\Validation\ValidationException;

/**
 * Result of a router match.
 * Responsible for parameter hydration and handler execution.
 */
class RouteMatch
{
    private int $status;
    /** @var callable|array<string>|null */
    private $handler;
    /** @var array<string, string> */
    private array $params;
    /** @var array<string, array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> */
    private array $paramCache;
    /** @var array<string, object> */
    private array $instanceCache;
    private ?ContainerInterface $container;
    /** @var array<string, mixed>|null Pre-validated data from native core */
    private ?array $ffiData;

    /**
     * @param array{int, callable|array<string>, array<string, string>} $info
     * @param array<string, array<array{name: string, type: ?string, isBuiltin: bool, isDTO: bool, isRequest: bool, hasDefault: bool, defaultValue: mixed}>> $paramCache
     * @param array<string, object> $instanceCache
     * @param array<string, mixed>|null $ffiData
     */
    public function __construct(
        array $info,
        array $paramCache = [],
        array $instanceCache = [],
        ?ContainerInterface $container = null,
        ?array $ffiData = null
    ) {
        $this->status        = $info[0];
        $this->handler       = $info[1];
        $this->params        = $info[2];
        $this->paramCache    = $paramCache;
        $this->instanceCache = $instanceCache;
        $this->container     = $container;
        $this->ffiData       = $ffiData;
    }

    public function isFound(): bool
    {
        return $this->status === RouterStatus::FOUND;
    }

    public function isMethodNotAllowed(): bool
    {
        return $this->status === RouterStatus::METHOD_NOT_ALLOWED;
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return (array)($this->handler ?? []);
    }

    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Execute the handler with injected parameters.
     * Zero-reflection execution using cached parameter maps.
     */
    public function execute(Request $request): mixed
    {
        if ($this->handler === null) {
            throw new \RuntimeException('Cannot execute a null handler.');
        }

        $handler = $this->handler;
        $key     = '';

        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $class  = is_scalar($handler[0]) ? (string)$handler[0] : '';
            /** @var string $method */
            $method = is_scalar($handler[1]) ? (string)$handler[1] : '';
            $key    = "$class::$method";

            if ($this->container && $this->container->has($class)) {
                $instance = $this->container->get($class);
            } else {
                if (!class_exists($class)) {
                    throw new \RuntimeException("Handler class '{$class}' not found.");
                }
                /** @var object $instance */
                $instance = $this->instanceCache[$class] ??= new $class();
            }

            if (!is_object($instance) || !method_exists($instance, $method)) {
                throw new \RuntimeException("Method '{$method}' not found on handler class '{$class}'.");
            }

            $args = $this->resolveArgs($key, $request);
            /** @phpstan-ignore-next-line */
            return $instance->$method(...$args);
        }

        if ($handler instanceof \Closure) {
            $key = 'closure_' . spl_object_id($handler);
            $args = $this->resolveArgs($key, $request);
            return $handler(...$args);
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveArgs(string $key, Request $request): array
    {
        if (!isset($this->paramCache[$key])) {
            return [];
        }

        $args = [];
        foreach ($this->paramCache[$key] as $param) {
            $name    = $param['name'];
            $type    = $param['type'];
            $isBuiltin = $param['isBuiltin'];

            // 1. Path Params
            if (isset($this->params[$name])) {
                $args[] = $this->cast($this->params[$name], (string)$type);
                continue;
            }

            // 2. Request Object
            if ($param['isRequest']) {
                $args[] = $request;
                continue;
            }

            // 3. DTO Validation & Hydration
            if ($param['isDTO']) {
                /** @var class-string<DTO> $type */
                if ($this->ffiData !== null) {
                    // Unified Hot Path: Use pre-validated data from Quill core
                    $dto = new $type();
                    $dtoMap = Validator::getCache($type);
                    foreach ($this->ffiData as $k => $v) {
                        if (isset($dtoMap[$k])) {
                            $dto->$k = $this->cast($v, $dtoMap[$k]['type']);
                        }
                    }
                    $args[] = $dto;
                } else {
                    // Standard Path: Validate from request input
                    $input = $request->input();
                    $args[] = Validator::validate($type, $input);
                }
                continue;
            }

            // 4. Default Values
            if ($param['hasDefault']) {
                $args[] = $param['defaultValue'];
                continue;
            }

            // 5. Container Injection
            if ($type && !$isBuiltin && $this->container?->has($type)) {
                $args[] = $this->container->get($type);
                continue;
            }

            $args[] = null;
        }

        return $args;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => is_scalar($value) ? (int)$value : 0,
            'float'  => is_scalar($value) ? (float)$value : 0.0,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => is_scalar($value) ? (string)$value : '',
            default  => $value,
        };
    }
}
