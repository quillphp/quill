<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Runtime;

class FFIFailureTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    #[Test]
    public function it_throws_exception_when_ffi_not_initialized(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FFI not initialized');
        
        Runtime::get();
    }

    #[Test]
    public function it_reports_not_available_when_not_initialized(): void
    {
        $this->assertFalse(Runtime::isAvailable());
    }

    #[Test]
    public function it_can_be_reset(): void
    {
        Runtime::reset();
        $this->assertFalse(Runtime::isAvailable());
    }
}
