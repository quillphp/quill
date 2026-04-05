<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Quill\Validation\Validator;
use Quill\Routing\Router;

final class Runtime
{
    private static ?\FFI $ffi = null;
    private static bool $available = false;
    /** @phpstan-ignore-next-line */
    private static bool $initialized = false;

    /**
     * Attempts to automatically discover and initialize the native runtime.
     */
    public static function boot(): bool
    {
        if (self::$available) {
            return true;
        }

        $libName = PHP_OS_FAMILY === 'Darwin' ? 'libquill.dylib' : 'libquill.so';
        $headerName = 'quill.h';

        $candidates = [
            // 1. Environment Variable
            fn() => ($binary = getenv('QUILL_CORE_BINARY')) 
                ? [(string) $binary, (string) (getenv('QUILL_CORE_HEADER') ?: dirname((string) $binary) . '/' . $headerName)] 
                : null,

            // 2. Local Vendor (Path Repository / standard install)
            fn() => [
                dirname(__DIR__, 2) . '/vendor/quillphp/quill-core/bin/' . $libName,
                dirname(__DIR__, 2) . '/vendor/quillphp/quill-core/bin/' . $headerName
            ],

            // 3. System Level
            fn() => [
                '/usr/local/lib/' . $libName,
                '/usr/local/include/' . $headerName
            ]
        ];

        foreach ($candidates as $candidateLoader) {
            $paths = $candidateLoader();
            if (!$paths) continue;
            
            $soPath = (string) $paths[0];
            $headerPath = (string) $paths[1];

            if (file_exists($soPath) && file_exists($headerPath)) {
                self::init($soPath, $headerPath);
                if (self::$available) {
                    return true;
                }
            } else {
                error_log(sprintf("[Quill] Candidate failed: lib=%s (%s), header=%s (%s)", 
                    $soPath, file_exists($soPath) ? 'found' : 'missing',
                    $headerPath, file_exists($headerPath) ? 'found' : 'missing'
                ));
            }
        }

        return false;
    }

    /**
     * @param string $soPath Absolute path to libquill.so
     * @param string $headerPath Absolute path to quill.h
     */
    public static function init(string $soPath, string $headerPath): void
    {
        if (self::$available) {
            return;
        }
        self::$initialized = true;

        if (!extension_loaded('ffi')) {
            return;
        }

        if (!file_exists($soPath) || !file_exists($headerPath)) {
            return;
        }

        try {
            $header = file_get_contents($headerPath);
            if ($header === false) {
                error_log("[Quill] Failed to read header at $headerPath");
                return;
            }
            /** @phpstan-ignore-next-line */
            self::$ffi = \FFI::cdef($header, $soPath);
            self::$available = true;
        } catch (\Throwable $e) {
            error_log('[Quill] FFI load failed for ' . $soPath . ': ' . $e->getMessage());
        }
    }

    public static function isAvailable(): bool
    {
        return self::$available;
    }

    public static function isStarted(): bool
    {
        return self::$available && getenv('QUILL_RUNTIME') === 'rust';
    }

    public static function get(): \FFI
    {
        if (self::$ffi === null) {
            throw new \RuntimeException('FFI not initialized. Call Runtime::init() first.');
        }
        return self::$ffi;
    }

    /** Reset for testing only — do not call in production code. */
    public static function reset(): void
    {
        if (class_exists(Validator::class)) {
            Validator::reset();
        }

        self::$ffi = null;
        self::$available = false;
        self::$initialized = false;
    }
}
