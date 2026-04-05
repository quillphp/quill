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
        $outBuf   = $ffi->new('char[4096]');
        
        /** @phpstan-ignore-next-line */
        $res = $ffi->quill_validator_validate(
            $registry, 
            $dtoClass, strlen($dtoClass), 
            $json, strlen($json), 
            $outBuf, 4096
        );

        if ($res === 1) { // Validation Error
            $errorJson = \FFI::string($outBuf);
            /** @var array<string, array<string>> $errors */
            $errors    = json_decode($errorJson, true, 512, JSON_THROW_ON_ERROR);
            throw new ValidationException($errors);
        }

        if ($res === 2) { // System Error
             throw new \RuntimeException("Quill Validation Engine System Error");
        }

        /** @var T $dto */
        $dto = new $dtoClass();
        $map = self::$cache[$dtoClass];
        
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

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
     * Reinitialize the validator registry for a freshly forked worker process.
     *
     * After pcntl_fork() every child has a COW copy of the parent's
     * Arc<ValidatorRegistry> pointer.  We free that copy here and create a
     * brand-new registry owned solely by this process, then re-register all
     * DTOs that were cached before the fork.
     */
    public static function reinitialize(): void
    {
        // Free the inherited (COW) Rust handle in this process.
        if (self::$handle !== null) {
            try {
                /** @phpstan-ignore-next-line */
                Runtime::get()->quill_validator_free(self::$handle);
            } catch (\Throwable) {
            }
            self::$handle = null;
        }

        // Remember which DTO classes were already reflected so we can re-register them.
        $cachedClasses = array_keys(self::$cache);
        // Clear the cache so register() treats each class as new.
        self::$cache = [];

        // Create a fresh Rust registry owned by this process.
        $ffi = Runtime::get();
        /** @phpstan-ignore-next-line */
        self::$handle = $ffi->quill_validator_new();

        // Re-register every DTO with the new registry.
        foreach ($cachedClasses as $dtoClass) {
            self::register($dtoClass);
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
