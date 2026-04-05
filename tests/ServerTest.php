<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Server;
use Quill\Routing\Router;
use Quill\Runtime\DriverInterface;
use Quill\Validation\Validator;

class ServerTest extends TestCase
{
    #[Test]
    public function it_boots_worker_correctly(): void
    {
        $router = $this->createMock(Router::class);
        $driver = $this->createMock(DriverInterface::class);
        
        // bootWorker() should recompile the router
        $router->expects($this->once())->method('recompile');
        
        $server = new Server($router, $driver);
        $method = new \ReflectionMethod($server, 'bootWorker');
        $method->setAccessible(true);
        
        $method->invoke($server);
    }

    #[Test]
    public function it_exits_loop_when_not_running(): void
    {
        $router = $this->createMock(Router::class);
        $driver = $this->createMock(DriverInterface::class);
        
        $router->method('getHandle')->willReturn(new \stdClass()); 
        
        // Mock buffer allocations
        $driver->method('allocateIdBuffer')->willReturn(new \ArrayObject([0]));
        $driver->method('allocateHandlerIdBuffer')->willReturn(new \ArrayObject([0]));
        $driver->method('allocateParamsBuffer')->willReturn(new \stdClass());
        $driver->method('allocateDtoBuffer')->willReturn(new \stdClass());
        
        // Loop should exit because $running is false
        $driver->expects($this->never())->method('poll');
        
        $server = new Server($router, $driver);
        $runningRef = new \ReflectionProperty($server, 'running');
        $runningRef->setAccessible(true);
        $runningRef->setValue($server, false);
        
        $loopMethod = new \ReflectionMethod($server, 'runEventLoop');
        $loopMethod->setAccessible(true);
        
        $loopMethod->invoke($server);
    }

    #[Test]
    public function it_handles_prebind_failure(): void
    {
        $router = $this->createMock(Router::class);
        $driver = $this->createMock(DriverInterface::class);
        
        $driver->method('prebind')->willReturn(-1);
        
        // Setup environment for multi-worker
        putenv('QUILL_WORKERS=2');
        
        $server = new Server($router, $driver);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to pre-bind port 8080");
        
        try {
            $server->start(8080);
        } finally {
            putenv('QUILL_WORKERS'); // reset
        }
    }
}
