<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\SocketServer;
use Quill\Runtime\DriverInterface;

class SocketServerTest extends TestCase
{
    #[Test]
    public function it_dispatches_requests_to_native_core(): void
    {
        $router = new \stdClass();
        $validator = new \stdClass();
        $port = 9000;
        $driver = $this->createMock(DriverInterface::class);

        $driver->expects($this->once())
            ->method('allocateResponseBuffer')
            ->willReturn(new \stdClass());

        $driver->expects($this->once())
            ->method('dispatch')
            ->willReturn(strlen('{"message":"ok"}'));

        $driver->expects($this->once())
            ->method('getString')
            ->willReturn('{"message":"ok"}');

        $server = new SocketServer($router, $validator, $port, $driver);
        
        $method = new \ReflectionMethod($server, 'handle');
        $method->setAccessible(true);

        // Memory stream to simulate a socket connection
        $conn = fopen('php://memory', 'r+');
        if (!is_resource($conn)) {
            $this->fail('Failed to open memory stream');
        }
        fwrite($conn, "GET /hello HTTP/1.1\r\n\r\n");
        rewind($conn);

        $id = (int)$conn;
        $sockets = [$id => $conn];
        $args = [$conn, &$sockets];
        $method->invokeArgs($server, $args);

        $this->assertArrayNotHasKey($id, $sockets); // Verify connection was removed from list
    }
    
    #[Test]
    public function it_handles_native_dispatch_error(): void
    {
        $router = new \stdClass();
        $validator = new \stdClass();
        $driver = $this->createMock(DriverInterface::class);

        $driver->method('allocateResponseBuffer')->willReturn(new \stdClass());
        $driver->method('dispatch')->willReturn(-1);

        $server = new SocketServer($router, $validator, 9000, $driver);
        
        $method = new \ReflectionMethod($server, 'handle');
        $method->setAccessible(true);

        $conn = fopen('php://memory', 'r+');
        if (!is_resource($conn)) {
            $this->fail('Failed to open memory stream');
        }
        fwrite($conn, "GET /error HTTP/1.1\r\n\r\n");
        rewind($conn);

        $id = (int)$conn;
        $sockets = [$id => $conn];
        $args = [$conn, &$sockets];
        $method->invokeArgs($server, $args);

        $this->assertArrayNotHasKey($id, $sockets); // Verify connection was removed from list
    }
}
