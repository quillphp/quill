<?php

declare(strict_types=1);

namespace Quill\Contracts;

use Quill\Http\Request;

interface MiddlewareInterface
{
    /**
     * Process the request through this middleware.
     *
     * @param Request  $request The incoming request
     * @param callable $next    The next middleware/handler in the pipeline
     * @return mixed            The response
     */
    public function handle(Request $request, callable $next): mixed;
}
