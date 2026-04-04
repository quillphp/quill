<?php

declare(strict_types=1);

namespace Middlewares;

use Quill\Request;

class TestMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        // Pre-processing...
        $response = $next($request);
        // Post-processing...
        
        return $response;
    }
}