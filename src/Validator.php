<?php

declare(strict_types=1);

namespace Quill;

use ReflectionClass;
use ReflectionProperty;

/**
 * High-performance Validator for Quill.
 * Performs boot-time reflection and request-time validation.
 */
class Validator
{
    /** @var array<string, array<string, array{type: string, rules: array<object>, hasDefault: bool, defaultValue: mixed, isNullable: bool}>> */
    private static array $cache = [];

    /**
     * Reflect a DTO class and cache its metadata.
     * Paid ONCE at boot.
     */
    public static function register(string $dtoClass): void
    {
        if (isset(self::$cache[$dtoClass])) {
            return;
        }

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
                if ($instance instanceof Attributes\Nullable) {
                    $isNullable = true;
                }
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
    }

    /**
     * Validate and hydrate a DTO from an array.
     * ZERO reflection during request.
     *
     * @template T of DTO
     * @param class-string<T> $dtoClass
     * @param array<string, mixed> $data
     * @return T
     */
    public static function validate(string $dtoClass, array $data): DTO
    {
        if (!isset(self::$cache[$dtoClass])) {
            self::register($dtoClass);
        }

        /** @var array<string, array{type: string, rules: array<object>, hasDefault: bool, defaultValue: mixed, isNullable: bool}> $map */
        $map = self::$cache[$dtoClass];
        /** @var T $dto */
        $dto = new $dtoClass();
        $errors = [];

        foreach ($map as $name => $meta) {
            $exists = array_key_exists($name, $data);
            $value = $exists ? $data[$name] : ($meta['hasDefault'] ? $meta['defaultValue'] : null);
            
            if ($value === null) {
                if (!$meta['isNullable'] && !$meta['hasDefault']) {
                    $errors[$name][] = "Field '$name' is required.";
                }
                
                // If the field is allowed to be null, set it and move to next property
                if ($meta['isNullable'] || $meta['hasDefault']) {
                    $dto->$name = $value;
                    continue;
                }
            }

            foreach ($meta['rules'] as $rule) {
                if (method_exists($rule, 'validate')) {
                    if ($error = $rule->validate($name, $value)) {
                        $errors[$name][] = $error;
                    }
                }
            }

            if ($value !== null) {
                $dto->$name = self::cast($value, $meta['type']);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $dto;
    }

    /**
     * Type coercion for strict PHP properties.
     */
    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string)$value,
            default => $value,
        };
    }
}
