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

        // Use the same robust discovery logic as the main app
        $this->assertTrue(Runtime::boot());
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
