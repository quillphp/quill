<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Quill\Validation\Validator;
use Quill\Routing\Router;

final class Runtime
{
    private static ?\FFI $ffi = null;
    private static bool $available = false;
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
            fn() => getenv('QUILL_CORE_BINARY') ? [getenv('QUILL_CORE_BINARY'), dirname(getenv('QUILL_CORE_BINARY')) . '/' . $headerName] : null,

            // 2. Local Vendor (Path Repository / standard install)
            fn() => [
                dirname(__DIR__, 2) . '/vendor/quillphp/quill-core/bin/' . $libName,
                dirname(__DIR__, 2) . '/vendor/quillphp/quill-core/bin/' . $headerName
            ],

            // 3. Project Build Folder
            fn() => [
                dirname(__DIR__, 2) . '/build/' . $libName,
                dirname(__DIR__, 2) . '/build/' . $headerName
            ],

            // 4. System Level
            fn() => [
                '/usr/local/lib/' . $libName,
                '/usr/local/include/' . $headerName
            ]
        ];

        foreach ($candidates as $candidateLoader) {
            $paths = $candidateLoader();
            if ($paths && file_exists($paths[0]) && file_exists($paths[1])) {
                self::init($paths[0], $paths[1]);
                if (self::$available) {
                    return true;
                }
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
                return;
            }
            /** @phpstan-ignore-next-line */
            self::$ffi = \FFI::cdef($header, $soPath);
            self::$available = true;
        } catch (\Throwable $e) {
            error_log('[Quill] FFI load failed: ' . $e->getMessage());
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
