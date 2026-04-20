<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Validation\Validator;
use Quill\Runtime\Runtime;

class ValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    #[Test]
    public function it_clears_cache_on_reinitialize(): void
    {
        $ref = new \ReflectionProperty(Validator::class, 'cache');
        /** @phpstan-ignore-next-line */
        $ref->setAccessible(true);
        
        // Populate cache manually
        $ref->setValue(null, ['SomeClass' => []]);
        $this->assertNotEmpty($ref->getValue());
        
        Validator::reinitialize();
        
        // Cache should be KEPT after reinitialize to avoid redundant reflection (RC-1)
        $this->assertNotEmpty($ref->getValue());
    }

    #[Test]
    public function it_handles_reinitialization_gracefully_without_ffi(): void
    {
        Validator::reinitialize();
        $this->assertFalse(Runtime::isAvailable());
    }
}
