<?php

declare(strict_types=1);

namespace Quill;

/**
 * Middleware Pipeline - Implementation of the "Onion" pattern.
 */
class Pipeline
{
    /** @var array<callable(Request, callable): mixed> */
    private array $middlewares = [];

    /**
     * Add middleware to the stack.
     *
     * @param array<callable(Request, callable): mixed> $middlewares
     */
    public function send(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    /**
     * Execute the pipeline through all middlewares ending with the destination.
     */
    public function then(Request $request, \Closure $destination): mixed
    {
        // Fast path: no middlewares — skip array_reduce + closure allocation entirely.
        if (empty($this->middlewares)) {
            return $destination($request);
        }

        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            static function ($next, $middleware) {
                return static function ($request) use ($next, $middleware) {
                    return $middleware($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }
}
