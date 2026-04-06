<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\App;
use Quill\Http\Request;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    public function testHandlerResolutionFromContainer(): void
    {
        $app = new App(['route_cache' => false]);
        $container = new SimpleContainer([
            SomeDependency::class => new SomeDependency('injected'),
            HandlerWithDependency::class => new HandlerWithDependency(new SomeDependency('manual')),
        ]);

        $app->setContainer($container);
        $app->get('/test', [HandlerWithDependency::class, 'handle']);

        // Mock request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        ob_start();
        $app->handle();
        $response = (string) ob_get_clean();

        $this->assertStringContainsString('manual', $response);
    }

    public function testParameterResolutionFromContainer(): void
    {
        $app = new App(['route_cache' => false]);
        $dep = new SomeDependency('autowired');
        $container = new SimpleContainer([
            SomeDependency::class => $dep,
        ]);

        $app->setContainer($container);
        $app->get('/inject', function (SomeDependency $s) {
            return ['msg' => $s->getValue()];
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/inject';

        ob_start();
        $app->handle();
        $response = (string) ob_get_clean();

        $this->assertStringContainsString('autowired', $response);
    }

    public function testMiddlewareResolutionFromContainer(): void
    {
        $app = new App(['route_cache' => false]);
        $container = new SimpleContainer([
            MiddlewareWithDependency::class => new MiddlewareWithDependency(new SomeDependency('via-middleware')),
        ]);

        $app->setContainer($container);
        $app->use(MiddlewareWithDependency::class);
        $app->get('/mid', fn() => ['ok' => true]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/mid';

        ob_start();
        $app->handle();
        $response = (string) ob_get_clean();

        $this->assertStringContainsString('via-middleware', $response);
    }
}

class SomeDependency
{
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
}

class HandlerWithDependency
{
    public function __construct(private SomeDependency $dep) {}
    /** @return array<string, mixed> */
    public function handle(): array { return ['val' => $this->dep->getValue()]; }
}

class MiddlewareWithDependency
{
    public function __construct(private SomeDependency $dep) {}
    public function handle(Request $request, callable $next): mixed {
        $response = $next($request);
        if (is_array($response)) {
            $response['mid'] = $this->dep->getValue();
        }
        return $response;
    }
}

class SimpleContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(private array $entries = []) {}
    public function get(string $id): mixed { return $this->entries[$id]; }
    public function has(string $id): bool { return isset($this->entries[$id]); }
}
