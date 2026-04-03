<?php

declare(strict_types=1);

namespace Quill;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

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

            $openapi['paths'][$normalizedPath][$method] = $operation;
        }

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
     * @param array<string> $handler
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $openapi
     */
    private function analyzeArrayHandler(array $handler, array &$operation, array &$openapi): void
    {
        [$class, $method] = $handler;
        if (!class_exists($class)) return;

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
        if (isset($openapi['components']['schemas'][$name])) return;

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
                $propSchema['nullable'] = true;
            }

            $schema['properties'][$propName] = $propSchema;

            // Check if required (missing default value AND not nullable)
            if (! $prop->hasDefaultValue() && ! $isNullable) {
                $schema['required'][] = $propName;
            }
        }

        if (empty($schema['required'])) unset($schema['required']);

        $openapi['components']['schemas'][$name] = $schema;
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
