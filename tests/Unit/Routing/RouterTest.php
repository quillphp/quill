<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Quill\Runtime\Runtime;
use Quill\Routing\Router;
use Quill\Routing\RouteMatch;

class RouterTest extends TestCase
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

    /** @test */
    public function it_can_register_and_dispatch_routes_via_core(): void
    {
        $router = new Router();
        $router->addRoute('GET', '/quill/{id}', fn($id) => "Quill $id");
        $router->compile();

        $match = $router->dispatch('GET', '/quill/456');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertTrue($match->isFound());
        $this->assertSame('456', $match->getParams()['id']);
    }

    /** @test */
    public function it_handles_not_found_via_core(): void
    {
        $router = new Router();
        $router->addRoute('GET', '/quill/{id}', fn($id) => "Quill $id");
        $router->compile();

        $match = $router->dispatch('GET', '/non-existent');

        $this->assertFalse($match->isFound());
    }

    /** @test */
    public function it_handles_method_not_allowed_via_core(): void
    {
        $router = new Router();
        $router->addRoute('GET', '/quill/{id}', fn($id) => "Quill $id");
        $router->compile();

        $match = $router->dispatch('POST', '/quill/123');

        $this->assertTrue($match->isMethodNotAllowed());
    }
}
