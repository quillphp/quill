<?php

declare(strict_types=1);

namespace Quill;
 
 use Psr\Container\ContainerInterface;

/**
 * Middleware Pipeline - Implementation of the "Onion" pattern.
 */
class Pipeline
{
    /** @var array<mixed> */
    private array $middlewares = [];
    private ?ContainerInterface $container = null;

    /**
     * Add middleware to the stack.
     *
     * @param array<callable|class-string> $middlewares
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

        /** @var callable(Request): mixed $pipeline */
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function (callable $next, $middleware) {
                return function (Request $request) use ($next, $middleware) {
                    if (is_string($middleware) && $this->container?->has($middleware)) {
                        $middleware = $this->container->get($middleware);
                    }

                    if (is_object($middleware) && method_exists($middleware, 'handle')) {
                        return $middleware->handle($request, $next);
                    }

                    if (is_callable($middleware)) {
                        return $middleware($request, $next);
                    }

                    throw new \RuntimeException('Invalid middleware.');
                };
            },
            $destination
        );

        return $pipeline($request);
    }
}
