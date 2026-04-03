<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Pipeline;
use Quill\Request;

class MiddlewareTest extends TestCase
{
    public function testPipelineExecutionOrder()
    {
        $pipeline = new Pipeline();
        $request = new Request();
        
        $middlewares = [
            function ($req, $next) {
                $result = $next($req);
                return "M1(" . $result . ")";
            },
            function ($req, $next) {
                $result = $next($req);
                return "M2(" . $result . ")";
            }
        ];

        $result = $pipeline->send($middlewares)->then($request, function ($req) {
            return "Handler";
        });

        // M1 runs first, calls next (M2), M2 calls next (Handler)
        // Handler returns "Handler", M2 returns "M2(Handler)", M1 returns "M1(M2(Handler))"
        $this->assertEquals('M1(M2(Handler))', $result);
    }

    public function testMiddlewareShortCircuit()
    {
        $pipeline = new Pipeline();
        $request = new Request();
        
        $middlewares = [
            function ($req, $next) {
                return "Blocked";
            },
            function ($req, $next) {
                return $next($req);
            }
        ];

        $result = $pipeline->send($middlewares)->then($request, function ($req) {
            return "Handler";
        });

        $this->assertEquals('Blocked', $result);
    }
}
