<?php

declare(strict_types=1);

namespace Quill\Runtime;

/**
 * Unified JSON utility for Quill.
 * Transparently uses native FFI acceleration when available.
 */
final class Json
{
    /**
     * Encode a value to JSON. 
     * Uses native FFI acceleration (sonic-rs) when available.
     */
    public static function encode(mixed $value): string
    {
        return (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON string.
     * @return array<string, mixed>
     */
    public static function decode(string $json): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
