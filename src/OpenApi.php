<?php

declare(strict_types=1);

namespace Quill;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Quill\Validation\DTO;

/**
 * High-performance OpenAPI 3.1 Schema Generator for Quill.
 */
class OpenApi
{
    /**
     * @param array<array{string, string, callable|array<string>}> $handlers
     * @return array<string, mixed>
     */
    public function generate(array $handlers): array
    {
        $cachePath = getcwd() . '/tmp/cache';
        $cacheFile = $cachePath . '/openapi.json';
        $routesFile = getcwd() . '/routes.php';

        // ENH-3: Cache validation via mtime
        if (file_exists($cacheFile) && file_exists($routesFile)) {
            if (filemtime($cacheFile) >= filemtime($routesFile)) {
                $cached = json_decode((string)file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    /** @var array<string, mixed> $cached */
                    return $cached;
                }
            }
        }

        $openapi = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Quill API',
                'version' => '1.0.0',
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
            ],
        ];

        foreach ($handlers as [$method, $path, $handler]) {
            // Skip docs routes
            if (str_starts_with($path, '/docs')) continue;

            $method = strtolower($method);
            $normalizedPath = $this->normalizePath($path);
            
            $operation = [
                'responses' => [
                    '200' => ['description' => 'OK'],
                ],
            ];

            // Path Parameters
            $params = $this->extractParams($path);
            if (!empty($params)) {
                $operation['parameters'] = [];
                foreach ($params as $param) {
                    $operation['parameters'][] = [
                        'name' => $param,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ];
                }
            }

            // Handler Metadata (DTOs, Return Types)
            if (is_array($handler)) {
                $this->analyzeArrayHandler($handler, $operation, $openapi);
            }

            /** @var array<string, array<string, mixed>> $paths */
            $paths = $openapi['paths'];
            $paths[$normalizedPath][$method] = $operation;
            $openapi['paths'] = $paths;
        }

        // ENH-3: Save generated schema to cache
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }
        file_put_contents($cacheFile, json_encode($openapi, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $openapi;
    }

    private function normalizePath(string $path): string
    {
        // Convert {param} to OpenAPI {param} (Quill already uses this format)
        return $path;
    }

    /** @return list<string> */
    private function extractParams(string $path): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
        return $matches[1];
    }

    /**
     * @param array<mixed> $handler
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $openapi
     */
    private function analyzeArrayHandler(array $handler, array &$operation, array &$openapi): void
    {
        $class = is_string($handler[0]) ? $handler[0] : '';
        $method = is_string($handler[1]) ? $handler[1] : '';
        if (!class_exists($class) || !method_exists($class, $method)) return;

        $reflection = new ReflectionMethod($class, $method);
        
        // Find DTO in parameters
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $dtoClass = $type->getName();
                if (is_subclass_of($dtoClass, DTO::class)) {
                    $schemaName = $this->getClassName($dtoClass);
                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/$schemaName"],
                            ],
                        ],
                    ];
                    $this->addSchema($dtoClass, $openapi);
                }
            }
        }
    }

    /** @param array<string, mixed> $openapi */
    private function addSchema(string $dtoClass, array &$openapi): void
    {
        $name = $this->getClassName($dtoClass);
        $components = is_array($openapi['components'] ?? null) ? $openapi['components'] : [];
        $schemas = is_array($components['schemas'] ?? null) ? $components['schemas'] : [];
        if (isset($schemas[$name])) return;
        
        /** @var class-string $dtoClass */
        $reflection = new ReflectionClass($dtoClass);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($properties as $prop) {
            $propName = $prop->getName();
            $type = $prop->getType();
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : 'string';
            $isNullable = $type ? $type->allowsNull() : false;

            $propSchema = $this->mapType($typeName);
            if ($isNullable) {
                /** @var array<string, mixed> $propSchema */
                $propSchema['nullable'] = true;
            }

            $schema['properties'][$propName] = $propSchema;

            // Check if required (missing default value AND not nullable)
            if (! $prop->hasDefaultValue() && ! $isNullable) {
                $schema['required'][] = $propName;
            }
        }

        if (empty($schema['required'])) unset($schema['required']);

        $components = is_array($openapi['components']) ? $openapi['components'] : [];
        $schemas = isset($components['schemas']) && is_array($components['schemas']) ? $components['schemas'] : [];
        $schemas[$name] = $schema;
        $components['schemas'] = $schemas;
        $openapi['components'] = $components;
    }

    private function getClassName(string $fullClass): string
    {
        $parts = explode('\\', $fullClass);
        return end($parts);
    }

    /** @return array<string, string> */
    private function mapType(string $phpType): array
    {
        return match ($phpType) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            default => ['type' => 'string'],
        };
    }
}
