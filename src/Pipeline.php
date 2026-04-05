<?php

declare(strict_types=1);

namespace Quill;

use Psr\Container\ContainerInterface;
use Quill\Http\Request;

/**
 * Middleware Pipeline - Implementation of the "Onion" pattern.
 */
class Pipeline
{
    /** @var array<callable|class-string|object> */
    private array $middlewares = [];
    private ?ContainerInterface $container = null;

    /**
     * Add middleware to the stack.
     *
     * @param array<callable|class-string|object> $middlewares
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
