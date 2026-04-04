<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Cors;
use Quill\Request;

class CorsTest extends TestCase
{
    public function testOptionsRequestShortCircuitsWithoutCallingNext(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $handlerCalled = false;
        $middleware    = Cors::middleware();

        ob_start();
        $result = $middleware(new Request(), function () use (&$handlerCalled) {
            $handlerCalled = true;
            return 'reached';
        });
        ob_end_clean();

        $this->assertFalse($handlerCalled);
        $this->assertEquals([], $result);
    }

    public function testNonOptionsRequestPassesThroughToHandler(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'http://example.com';

        $middleware = Cors::middleware(['origins' => ['http://example.com']]);

        ob_start();
        $result = $middleware(new Request(), fn() => 'handler_result');
        ob_end_clean();

        $this->assertEquals('handler_result', $result);
    }

    public function testWildcardOriginAllowsAnyOrigin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN']    = 'http://random.com';

        $middleware = Cors::middleware(['origins' => ['*']]);

        ob_start();
        $result = $middleware(new Request(), fn() => 'ok');
        ob_end_clean();

        $this->assertEquals('ok', $result);
    }

    public function testAppHandlesOptionsPreflightWithCors(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI']    = '/users';
        $_SERVER['HTTP_ORIGIN']    = 'http://example.com';

        $app = new \Quill\App(['route_cache' => false]);
        $app->use(Cors::middleware(['origins' => ['http://example.com']]));
        $app->get('/users', fn() => 'users_list');

        ob_start();
        $app->handle();
        $output = ob_get_clean();

        // If Cors middleware caught it, it should return [] which encodes to '' (zero bytes)
        $this->assertEquals(204, http_response_code());
        $this->assertEquals('', $output);
    }
}
