<?php

declare(strict_types=1);

namespace Quill\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Server;
use Quill\Routing\Router;
use Quill\Runtime\DriverInterface;
use Quill\Http\Request;

class RequestLifecycleTest extends TestCase
{
    #[Test]
    public function it_executes_full_request_lifecycle_with_mock_driver(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([
            0 => ['GET', '/test', function() {
                return ['status' => 'ok'];
            }]
        ]);
        $router->method('getHandle')->willReturn(new \stdClass());
        
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('allocateIdBuffer')->willReturn(new \ArrayObject([123]));
        $driver->method('allocateHandlerIdBuffer')->willReturn(new \ArrayObject([0]));
        $driver->method('allocateParamsBuffer')->willReturn(new \stdClass());
        $driver->method('allocateDtoBuffer')->willReturn(new \stdClass());
        
        $server = new Server($router, $driver);
        
        // Use a callback to return success once, then stop the loop
        $driver->expects($this->exactly(2))
            ->method('poll')
            ->willReturnCallback(function() use ($server) {
                static $called = false;
                if (!$called) {
                    $called = true;
                    return 1;
                }
                $ref = new \ReflectionProperty($server, 'running');
                /** @phpstan-ignore-next-line */
                $ref->setAccessible(true);
                $ref->setValue($server, false);
                return 0;
            });

        $driver->method('getString')->willReturn('{}'); // Empty JSON for params/dto

        $driver->expects($this->once())
            ->method('respond')
            ->with(123, $this->callback(function($json) {
                $res = json_decode((string)$json, true);
                return is_array($res) && 
                       isset($res['status'], $res['body']) && 
                       $res['status'] === 200 && 
                       is_string($res['body']) &&
                       str_contains($res['body'], '"status":"ok"');
            }));

        // No need to manually set handle anymore as we are mocking getHandle()

        $method = new \ReflectionMethod($server, 'runEventLoop');
        /** @phpstan-ignore-next-line */
        $method->setAccessible(true);
        $method->invoke($server);
    }
}
