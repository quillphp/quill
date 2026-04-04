<?php

declare(strict_types=1);

namespace Quill\Concerns;

use Quill\App;

/**
 * Routing logic for the App class.
 */
trait HandlesRouting
{
    /** @var array<array{string, string, callable|array<string>}> */
    private array $handlers = [];
    /** @var array<int, string> */
    private array $groupStack = [];

    /**
     * Map a handler to multiple HTTP methods.
     * @param array<string> $methods
     * @param callable|array<string> $handler
     */
    public function map(array $methods, string $path, callable|array $handler): void
    {
        $prefix = implode('', $this->groupStack);
        foreach ($methods as $method) {
            $this->handlers[] = [strtoupper($method), $prefix . $path, $handler];
        }
    }

    /**
     * Register a route group with a common prefix.
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupStack[] = $prefix;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Map a full RESTful resource to a handler class.
     * Maps standard verbs to: index, store, show, update, destroy.
     */
    public function resource(string $path, string $handlerClass): void
    {
        $this->get($path, [$handlerClass, 'index']);
        $this->post($path, [$handlerClass, 'store']);
        $this->get("$path/{id}", [$handlerClass, 'show']);
        $this->put("$path/{id}", [$handlerClass, 'update']);
        $this->patch("$path/{id}", [$handlerClass, 'update']);
        $this->delete("$path/{id}", [$handlerClass, 'destroy']);
    }

    /**
     * Map a GET route.
     * @param callable|array<string> $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->map(['GET'], $path, $handler);
    }

    /**
     * Map a POST route.
     * @param callable|array<string> $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->map(['POST'], $path, $handler);
    }

    /**
     * Map a PUT route.
     * @param callable|array<string> $handler
     */
    public function put(string $path, callable|array $handler): void
    {
        $this->map(['PUT'], $path, $handler);
    }

    /**
     * Map a DELETE route.
     * @param callable|array<string> $handler
     */
    public function delete(string $path, callable|array $handler): void
    {
        $this->map(['DELETE'], $path, $handler);
    }

    /**
     * Map a PATCH route.
     * @param callable|array<string> $handler
     */
    public function patch(string $path, callable|array $handler): void
    {
        $this->map(['PATCH'], $path, $handler);
    }

    /**
     * Map a HEAD route (explicit handler override).
     * @param callable|array<string> $handler
     */
    public function head(string $path, callable|array $handler): void
    {
        $this->map(['HEAD'], $path, $handler);
    }

    /**
     * Map an OPTIONS route (explicit handler override).
     * @param callable|array<string> $handler
     */
    public function options(string $path, callable|array $handler): void
    {
        $this->map(['OPTIONS'], $path, $handler);
    }

    /**
     * @return array<array{string, string, callable|array<string>}>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
