<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Router;
use Quill\Request;

class UserController
{
    public function show(int $id)
    {
        return "User ID: $id";
    }
}

class RouterTest extends TestCase
{
    public function testRouteRegistrationAndDispatch()
    {
        $router = new Router();
        $router->addRoute('GET', '/users/{id}', [UserController::class, 'show']);
        
        $match = $router->dispatch('GET', '/users/123');
        $this->assertTrue($match->isFound());
        $this->assertFalse($match->isNotFound());
    }

    public function testRouteExecuteWithParamInjection()
    {
        $router = new Router();
        $router->addRoute('GET', '/user/{id}', [UserController::class, 'show']);
        
        $match = $router->dispatch('GET', '/user/456');
        $request = new Request();
        
        $result = $match->execute($request);
        $this->assertEquals('User ID: 456', $result);
    }

    public function testMethodNotAllowed()
    {
        $router = new Router();
        $router->addRoute('POST', '/login', function() { return 'ok'; });

        $match = $router->dispatch('GET', '/login');
        $this->assertTrue($match->isMethodNotAllowed());
    }

    public function testClosureHandlerReceivesInjectedPathParam()
    {
        $router = new Router();
        $router->addRoute('GET', '/items/{id}', function (int $id) {
            return "Item: $id";
        });

        $match  = $router->dispatch('GET', '/items/789');
        $result = $match->execute(new Request());
        $this->assertEquals('Item: 789', $result);
    }

    public function testClosureHandlerReceivesRequest()
    {
        $router = new Router();
        $router->addRoute('GET', '/ping', function (Request $req) {
            return 'pong';
        });

        $match  = $router->dispatch('GET', '/ping');
        $result = $match->execute(new Request());
        $this->assertEquals('pong', $result);
    }
}
