<?php

declare(strict_types=1);

/**
 * In-process micro-benchmark for QuillPHP.
 *
 * Measures pure framework overhead per request with zero HTTP cost.
 * Run with JIT for the most representative numbers:
 *
 *   php -d opcache.enable_cli=1 -d opcache.jit=tracing \
 *       -d opcache.jit_buffer_size=256M scripts/benchmark.php
 *
 * Or via GitHub Actions (bench-inprocess job) where OPcache + JIT are already
 * configured in the ini-values step.
 */

require __DIR__ . '/../vendor/autoload.php';

use Handlers\BenchHandler;
use Quill\App;
use Quill\Request;

// ── App setup ────────────────────────────────────────────────────────────────
// route_cache disabled: in-process benchmark reuses the same booted App, so
// the in-memory dispatch tree is already warm — no disk I/O needed.
$app = new App(['route_cache' => false]);

$app->get('/hello',       [BenchHandler::class, 'hello']);
$app->get('/users/{id}',  [BenchHandler::class, 'user']);
$app->post('/echo',       [BenchHandler::class, 'echo']);
$app->post('/users',      [\Handlers\UserHandler::class, 'store']);

$app->boot();

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Run $iterations calls of $callback, print timing + req/s.
 * Returns the measured req/s as a float.
 */
function bench(string $label, int $iterations, callable $callback): float
{
    // Warmup pass — lets JIT trace before timing starts.
    for ($i = 0; $i < min(500, (int)($iterations / 10)); $i++) {
        $callback();
    }

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    $elapsed = (hrtime(true) - $start) / 1e9; // nanoseconds → seconds

    $rps = $iterations / $elapsed;
    printf(
        "%-40s  %6d iter  %7.4fs  %s req/s\n",
        $label,
        $iterations,
        $elapsed,
        number_format($rps)
    );
    return $rps;
}

// ── Request fixtures (created once, reused every iteration) ──────────────────
$reqHello = (new Request())->withMethod('GET')->withPath('/hello');
$reqUser  = (new Request())->withMethod('GET')->withPath('/users/42');
$reqEcho  = (new Request())->withMethod('POST')->withPath('/echo')
    ->withInput('{"email":"bench@quill.dev","name":"Quill","role":"admin"}');
$reqDto   = (new Request())->withMethod('POST')->withPath('/users')
    ->withInput('{"email":"bench@quill.dev","name":"Quill","role":"admin"}');

// ── Benchmark suite ───────────────────────────────────────────────────────────
echo str_repeat('─', 72) . "\n";
echo "QuillPHP In-Process Benchmark  (PHP " . PHP_VERSION . ")\n";
echo str_repeat('─', 72) . "\n\n";

$N = 100_000;

bench('GET /hello    (BenchHandler::hello)',   $N, static function () use ($app, $reqHello): void {
    ob_start();
    $app->handle($reqHello);
    ob_end_clean();
});

bench('GET /users/42 (BenchHandler::user)',    $N, static function () use ($app, $reqUser): void {
    ob_start();
    $app->handle($reqUser);
    ob_end_clean();
});

bench('POST /echo    (BenchHandler::echo)',    $N, static function () use ($app, $reqEcho): void {
    ob_start();
    $app->handle($reqEcho);
    ob_end_clean();
});

bench('POST /users   (UserHandler + DTO)',  50_000, static function () use ($app, $reqDto): void {
    ob_start();
    $app->handle($reqDto);
    ob_end_clean();
});

echo "\n" . str_repeat('─', 72) . "\n";
echo "Note: in-process numbers exclude HTTP parsing, TCP, and Swoole C layer.\n";
echo "      Multiply by Swoole worker count for a rough HTTP req/s estimate.\n";
echo str_repeat('─', 72) . "\n";
