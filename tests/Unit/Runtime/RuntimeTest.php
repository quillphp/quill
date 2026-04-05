<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Runtime;

class RuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    #[Test]
    public function it_loads_available_ffi_with_valid_lib(): void
    {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('FFI extension required');
        }

        $libName = PHP_OS_FAMILY === 'Darwin' ? 'libquill.dylib' : 'libquill.so';
        $vendorBin = getenv('QUILL_CORE_BINARY') 
            ? dirname((string) getenv('QUILL_CORE_BINARY')) . '/'
            : __DIR__ . '/../../../vendor/quillphp/quill-core/bin/';

        Runtime::init(
            soPath:     getenv('QUILL_CORE_BINARY') ?: $vendorBin . $libName,
            headerPath: getenv('QUILL_CORE_HEADER') ?: $vendorBin . 'quill.h'
        );

        $this->assertTrue(Runtime::isAvailable());
    }

    #[Test]
    public function it_fails_gracefully_with_missing_lib(): void
    {
        Runtime::init(
            soPath: __DIR__ . '/../../../build/missing.so',
            headerPath: __DIR__ . '/../../../quill.h'
        );

        $this->assertFalse(Runtime::isAvailable());
    }
}
