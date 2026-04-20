<?php

declare(strict_types=1);

namespace Quill\Validation;

use Quill\Runtime\Runtime;

/**
 * Handle-based Validator for Quill.
 * High-performance binary validation engine.
 * Mandatory Quill Core requirement.
 */
class Validator
{
    /** @var array<string, array<string, array{type: string, rules: array<object>, hasDefault: bool, defaultValue: mixed, isNullable: bool}>> */
    private static array $cache = [];

    /** @var \FFI\CData|null Pointer to the native validation registry */
    private static ?\FFI\CData $handle = null;

    private static int $errorBufferSize = 4096;

    /**
     * Set the buffer size for validation errors.
     */
    public static function setBufferSize(int $size): void
    {
        self::$errorBufferSize = $size;
    }

    /**
     * Reflect a DTO class and cache its metadata.
     * Paid ONCE at boot.
     *
     * @param class-string $dtoClass
     */
    public static function register(string $dtoClass): void
    {
        if (isset(self::$cache[$dtoClass])) {
            return;
        }

        /** @var class-string $dtoClass */
        $reflection = new \ReflectionClass($dtoClass);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $map = [];
        foreach ($properties as $prop) {
            $name = $prop->getName();
            $type = $prop->getType();
            $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : 'string';
            $isNullable = $type ? $type->allowsNull() : true;
            
            $attributes = $prop->getAttributes();
            $rules = [];
            foreach ($attributes as $attr) {
                $instance = $attr->newInstance();
                $rules[] = $instance;
            }

            $map[$name] = [
                'type' => $typeName,
                'rules' => $rules,
                'hasDefault' => $prop->hasDefaultValue(),
                'defaultValue' => $prop->hasDefaultValue() ? $prop->getDefaultValue() : null,
                'isNullable' => $isNullable,
            ];
        }

        self::$cache[$dtoClass] = $map;

        $registry = self::getRegistry();
        if ($registry === null) return;

        $ffi    = Runtime::get();
        $schema = self::buildSchema($dtoClass);
        $json   = (string)json_encode(['fields' => $schema]);

        /** @phpstan-ignore-next-line */
        $ffi->quill_validator_register(
            $registry, 
            $dtoClass, strlen($dtoClass), 
            $json, strlen($json)
        );
    }

    /**
     * Validate and hydrate a DTO from a JSON string.
     * ZERO reflection during request.
     *
     * @template T of DTO
     * @param class-string<T> $dtoClass
     * @param string $json
     * @return T
     */
    public static function validate(string $dtoClass, string $json): DTO
    {
        if (!isset(self::$cache[$dtoClass])) {
            self::register($dtoClass);
        }

        $ffi      = Runtime::get();
        $registry = self::getRegistry();
        
        /** @var \FFI\CData $outBuf */
        $outBuf   = $ffi->new("char[" . self::$errorBufferSize . "]");
        
        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_validator_validate(
            $registry, 
            $dtoClass, strlen($dtoClass), 
            $json, strlen($json), 
            $outBuf, self::$errorBufferSize
        );

        if ($res === ValidationStatus::VALIDATION_ERROR) { // Validation Error
            /** @var \FFI\CData $outBuf */
            $errorJson = \FFI::string($outBuf);
            if (strlen($errorJson) >= self::$errorBufferSize - 1) {
                fwrite(STDERR, "[Validator] Warning: Validation error message may have been truncated (buffer size: " . self::$errorBufferSize . ")\n");
            }
            /** @var array<string, array<string>> $errors */
            $errors    = json_decode($errorJson, true, 512, JSON_THROW_ON_ERROR);
            throw new ValidationException($errors);
        }

        if ($res === ValidationStatus::SYSTEM_ERROR) { // System Error
             throw new \RuntimeException("Quill Validation Engine System Error");
        }

        /** @var T $dto */
        $dto = new $dtoClass();
        $map = self::$cache[$dtoClass];
        
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON body: " . $e->getMessage(), 400);
        }

        foreach ($decoded as $name => $val) {
            if (isset($map[$name])) {
                $dto->{(string)$name} = self::cast($val, $map[$name]['type']);
            }
        }

        return $dto;
    }

    /**
     * Get the reflection cache for a DTO class.
     * @param class-string $dtoClass
     * @return array<string, array{type: string, rules: array<object>, hasDefault: bool, defaultValue: mixed, isNullable: bool}>
     */
    public static function getCache(string $dtoClass): array
    {
        if (!isset(self::$cache[$dtoClass])) {
            self::register($dtoClass);
        }
        return self::$cache[$dtoClass];
    }

    /**
     * Get the handle to the Quill validation registry.
     */
    public static function getRegistry(): ?\FFI\CData
    {
        if (self::$handle === null && Runtime::isAvailable()) {
            $ffi = Runtime::get();
            /** @phpstan-ignore-next-line */
            self::$handle = $ffi->quill_validator_new();
        }
        return self::$handle;
    }

    /**
     * Initialise the validator registry.
     */
    public static function reinitialize(): void
    {
        if (!Runtime::isAvailable()) {
            return;
        }

        if (self::$handle === null) {
            $ffi = Runtime::get();
            /** @phpstan-ignore-next-line */
            self::$handle = $ffi->quill_validator_new();
        }
    }

    /**
     * Reset the validator cache and registry.
     */
    public static function reset(): void
    {
        self::$cache = [];
        if (self::$handle !== null) {
            /** @phpstan-ignore-next-line */
            Runtime::get()->quill_validator_free(self::$handle);
            self::$handle = null;
        }
    }

    /**
     * Build a schema for the Quill engine.
     * @return array<string, mixed>
     */
    private static function buildSchema(string $dtoClass): array
    {
        /** @var class-string $dtoClass */
        $reflection = new \ReflectionClass($dtoClass);
        $schema     = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name   = $prop->getName();
            $type   = $prop->getType();
            $rules  = [];
            
            foreach ($prop->getAttributes() as $attr) {
                $ruleName = basename(str_replace('\\', '/', $attr->getName()));
                $params = $attr->getArguments();
                
                // Map PHP attribute arguments to Rust-expected keys
                $mappedParams = match ($ruleName) {
                    'Min', 'Max' => ['val' => (float)($params[0] ?? 0)],
                    'MinLength', 'MaxLength' => ['len' => (int)($params[0] ?? 0)],
                    'Regex' => ['pattern' => (string)($params[0] ?? '')],
                    default => []
                };

                $rules[] = array_merge(['type' => $ruleName], $mappedParams);
            }

            $schema[$name] = [
                'rules'         => $rules,
                'is_nullable'   => $type ? $type->allowsNull() : true,
                'has_default'   => $prop->hasDefaultValue(),
                'default_value' => $prop->hasDefaultValue() ? $prop->getDefaultValue() : null,
            ];
        }

        return $schema;
    }

    /**
     * Type coercion for strict PHP properties.
     */
    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'    => is_scalar($value) ? (int)$value : 0,
            'float'  => is_scalar($value) ? (float)$value : 0.0,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) ? (string)$value : '',
            default  => $value,
        };
    }
}
