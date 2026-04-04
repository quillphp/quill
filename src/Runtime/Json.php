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
        if (!Runtime::isStarted()) {
            return (string)json_encode($value, JSON_THROW_ON_ERROR);
        }

        $ffi   = Runtime::get();
        $input = (string)json_encode($value, JSON_THROW_ON_ERROR);
        $len   = strlen($input);
        
        // Oversize buffer for encoded output
        $outLen = $len + 1024;
        /** @var \FFI\CData $outBuf */
        $outBuf = $ffi->new("char[$outLen]");
        
        /** @phpstan-ignore-next-line */
        $written = $ffi->quill_json_compact($input, $len, $outBuf, $outLen);
        
        if ($written === 0) {
            return $input;
        }

        return \FFI::string($outBuf, (int)$written);
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
