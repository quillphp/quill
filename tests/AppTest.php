<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\App;
use Quill\Request;
use Quill\ValidationException;

class AppTest extends TestCase
{
    /**
     * Boot a fresh App with caching disabled (safe for isolated tests).
     */
    private function makeApp(array $config = []): App
    {
        return new App(array_merge(['route_cache' => false, 'debug' => false, 'env' => 'prod'], $config));
    }

    /**
     * Run App::handle() and capture the JSON output and status code.
     * @return array{body: array, status: int}
     */
    private function runHandle(App $app, ?Request $request = null): array
    {
        ob_start();
        $app->handle($request);
        $output = ob_get_clean();
        return [
            'body' => json_decode($output ?: '{}', true) ?? [],
            'status' => http_response_code()
        ];
    }

    public function testHandleReturns404ForUnknownRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/nonexistent';

        $app = $this->makeApp();
        $app->get('/users', fn() => []);

        $res = $this->runHandle($app);
        $this->assertEquals(404, $res['status']);
    }

    public function testHandleReturns405ForWrongMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI']    = '/users';

        $app = $this->makeApp();
        $app->get('/users', fn() => []);

        $res = $this->runHandle($app);
        $this->assertEquals(405, $res['status']);
    }

    public function testHandleReturns422WithFieldErrorsForInvalidDto()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/validate';

        $app = $this->makeApp();
        $app->get('/validate', function () {
            throw new ValidationException(['email' => ['The field email is required.']]);
        });

        $res = $this->runHandle($app);
        $this->assertEquals(422, $res['status']);
        $this->assertArrayHasKey('errors', $res['body']);
    }

    public function testHandleReturns400ForBadJson()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/echo';

        $app = $this->makeApp();
        $app->post('/echo', function (Request $req) {
            return $req->json();
        });

        $badRequest = (new Request())->withInput('{invalid json');
        $res = $this->runHandle($app, $badRequest);
        $this->assertEquals(400, $res['status']);
    }

    public function testHandleReturns500ForUnhandledError()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/boom';

        $app = $this->makeApp();
        $app->get('/boom', function () {
            throw new \RuntimeException('Unexpected!');
        });

        $res = $this->runHandle($app);
        $this->assertEquals(500, $res['status']);
    }

    public function testPatchRouteIsDispatchedCorrectly()
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI']    = '/update';

        $app = $this->makeApp();
        $app->patch('/update', fn() => ['updated' => true]);

        $res = $this->runHandle($app);
        $this->assertEquals(true, $res['body']['updated']);
        $this->assertEquals(200, $res['status']);
    }

    public function testHandleSupportsHttpResponseObject()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/users';

        $app = $this->makeApp();
        $app->post('/users', function () {
            return \Quill\HttpResponse::created(['id' => 1]);
        });

        $res = $this->runHandle($app);
        $this->assertEquals(201, $res['status']);
        $this->assertEquals(1, $res['body']['id']);
    }

    public function testNoContentResponseHasZeroBody()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI']    = '/users/1';

        $app = $this->makeApp();
        $app->delete('/users/{id}', fn() => \Quill\HttpResponse::noContent());

        ob_start();
        $app->handle();
        $output = ob_get_clean();

        $this->assertEquals(204, http_response_code());
        $this->assertEquals('', $output);
    }

    public function testMapRegistersMultipleMethods()
    {
        $app = $this->makeApp();
        $app->map(['GET', 'POST'], '/map-test', fn() => 'ok');

        $handlers = $app->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertEquals('GET', $handlers[0][0]);
        $this->assertEquals('POST', $handlers[1][0]);
    }

    public function testRouteGroupsPrependPrefix()
    {
        $app = $this->makeApp();
        $app->group('/api', function ($app) {
            $app->get('/users', fn() => 'users');
        });

        $handlers = $app->getHandlers();
        $this->assertEquals('/api/users', $handlers[0][1]);
    }

    public function testNestedRouteGroups()
    {
        $app = $this->makeApp();
        $app->group('/api', function ($app) {
            $app->group('/v1', function ($app) {
                $app->get('/users', fn() => 'v1_users');
            });
        });

        $handlers = $app->getHandlers();
        $this->assertEquals('/api/v1/users', $handlers[0][1]);
    }

    public function testResourceRegistersStandardCruds()
    {
        $app = $this->makeApp();
        $app->resource('/users', 'Handlers\UserHandler');

        $handlers = $app->getHandlers();
        // index, store, show, update, update, destroy
        $this->assertCount(6, $handlers);
        $this->assertEquals('GET',    $handlers[0][0]); $this->assertEquals('/users',     $handlers[0][1]);
        $this->assertEquals('POST',   $handlers[1][0]); $this->assertEquals('/users',     $handlers[1][1]);
        $this->assertEquals('GET',    $handlers[2][0]); $this->assertEquals('/users/{id}', $handlers[2][1]);
        $this->assertEquals('PUT',    $handlers[3][0]); $this->assertEquals('/users/{id}', $handlers[3][1]);
        $this->assertEquals('PATCH',  $handlers[4][0]); $this->assertEquals('/users/{id}', $handlers[4][1]);
        $this->assertEquals('DELETE', $handlers[5][0]); $this->assertEquals('/users/{id}', $handlers[5][1]);
    }
}

