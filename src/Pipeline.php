<?php

declare(strict_types=1);

namespace Quill;
 
 use Psr\Container\ContainerInterface;

/**
 * Middleware Pipeline - Implementation of the "Onion" pattern.
 */
class Pipeline
{
    /** @var array<callable(Request, callable): mixed> */
    private array $middlewares = [];
    private ?ContainerInterface $container = null;

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

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
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
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    if (is_string($middleware) && $this->container?->has($middleware)) {
                        $middleware = $this->container->get($middleware);
                    }
                    
                    if (is_object($middleware) && method_exists($middleware, 'handle')) {
                        return $middleware->handle($request, $next);
                    }

                    return $middleware($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }
}
