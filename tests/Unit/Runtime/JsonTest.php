<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Json;
use Quill\Runtime\Runtime;

class JsonTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
        Runtime::init(
            soPath:     __DIR__ . '/../../../build/libquill.so',
            headerPath: __DIR__ . '/../../../quill.h',
        );

        if (!Runtime::isAvailable()) {
            $this->markTestSkipped('Quill Core (libquill.so) required for tests.');
        }

        putenv('QUILL_RUNTIME=rust');
    }

    #[Test]
    public function it_encodes_json_via_core(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $json = Json::encode($data);
        
        $this->assertJson($json);
        $this->assertStringContainsString('"name":"John"', $json);
    }

    #[Test]
    public function it_decodes_json_via_php(): void
    {
        // Decode still uses PHP-standard json_decode for now
        $json = '{"name":"John","age":30}';
        $data = Json::decode($json);
        
        $this->assertSame('John', $data['name']);
        $this->assertSame(30, $data['age']);
    }
}
