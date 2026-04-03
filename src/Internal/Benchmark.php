<?php

declare(strict_types=1);

namespace Quill\Internal;

use Quill\App;
use Quill\Request;
use Handlers\BenchHandler;
use Handlers\UserHandler;

/**
 * Native Benchmarking Engine for Quill.
 * Integrated directly into the CLI for zero-file overhead.
 */
class Benchmark
{
    private App $app;

    public function __construct()
    {
        // route_cache disabled: reuses the same booted App instance
        $this->app = new App(['route_cache' => false]);
        
        // Register standard benchmark routes
        $this->app->get('/hello',      [BenchHandler::class, 'hello']);
        $this->app->get('/users/{id}', [BenchHandler::class, 'user']);
        $this->app->post('/echo',      [BenchHandler::class, 'echo']);
        $this->app->post('/users',     [UserHandler::class, 'store']);
        
        $this->app->boot();
    }

    public function run(): void
    {
        echo "\n \033[1m⚡ Quill Native In-Process Benchmark\033[0m (PHP " . PHP_VERSION . ")\n";
        echo " " . str_repeat('─', 68) . "\n\n";

        $iterations = 100_000;

        $reqHello = (new Request())->withMethod('GET')->withPath('/hello');
        $reqUser  = (new Request())->withMethod('GET')->withPath('/users/42');
        $reqEcho  = (new Request())->withMethod('POST')->withPath('/echo')
            ->withInput(json_encode(['email' => 'bench@quill.dev', 'name' => 'Quill', 'role' => 'admin']));
        $reqDto   = (new Request())->withMethod('POST')->withPath('/users')
            ->withInput(json_encode(['email' => 'bench@quill.dev', 'name' => 'Quill', 'role' => 'admin']));

        $this->bench('GET /hello    (BenchHandler::hello)', $iterations, function () use ($reqHello) {
            ob_start();
            $this->app->handle($reqHello);
            ob_end_clean();
        });

        $this->bench('GET /users/42 (BenchHandler::user)', $iterations, function () use ($reqUser) {
            ob_start();
            $this->app->handle($reqUser);
            ob_end_clean();
        });

        $this->bench('POST /echo    (BenchHandler::echo)', $iterations, function () use ($reqEcho) {
            ob_start();
            $this->app->handle($reqEcho);
            ob_end_clean();
        });

        $this->bench('POST /users   (UserHandler + DTO)', 50_000, function () use ($reqDto) {
            ob_start();
            $this->app->handle($reqDto);
            ob_end_clean();
        });

        echo "\n " . str_repeat('─', 68) . "\n";
        echo " \033[2mNote: numbers reflect pure framework overhead (zero network cost).\033[0m\n";
        echo " " . str_repeat('─', 68) . "\n\n";
    }

    private function bench(string $label, int $iterations, callable $callback): void
    {
        // Warmup pass
        for ($i = 0; $i < min(500, (int)($iterations / 10)); $i++) {
            $callback();
        }

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        $elapsed = (hrtime(true) - $start) / 1e9;
        $rps = $iterations / $elapsed;

        printf(
            " \033[36m%-32s\033[0m  %6d iter  %7.4fs  \033[32m%s req/s\033[0m\n",
            $label,
            $iterations,
            $elapsed,
            number_format($rps)
        );
    }
}
