<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Quill\Http\Request;

class RequestTest extends TestCase
{
    public function testPathStripsQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/users?id=123';
        $request = new Request();
        $this->assertEquals('/users', $request->path());
    }

    public function testMethodReturnsServerMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $request = new Request();
        $this->assertEquals('PATCH', $request->method());
    }

    public function testUrlEncodingInPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/hello%20world';
        $request = new Request();
        $this->assertEquals('/hello world', $request->path());
    }

    public function testInvalidJsonThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = (new Request())->withInput('{bad json');
        $request->json();
    }

    public function testOversizedPayloadThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = (new Request())->withInput(str_repeat('a', 3 * 1024 * 1024));
        $request->json();
    }
}
