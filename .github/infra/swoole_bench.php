<?php

declare(strict_types=1);

/**
 * Quill Swoole benchmark server.
 *
 * Runs the same benchmark routes as bench_server.php but through Quill's
 * native Swoole run mode: NTS PHP + JIT tracing + direct-write response
 * (no ob_start / headers_list bridge).
 *
 * Start via Docker:
 *   SWOOLE=1 bash scripts/bench_run.sh
 *
 * Or manually (requires swoole extension):
 *   SWOOLE_WORKERS=8 SWOOLE_PORT=8080 php scripts/swoole_bench.php
 *
 * For SWOOLE_BASE mode on Linux (no master-process dispatch, SO_REUSEPORT):
 *   SWOOLE_MODE=base SWOOLE_WORKERS=8 php scripts/swoole_bench.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Handlers\BenchHandler;
use Quill\App;

$app = new App(['route_cache' => false]);

$app->get('/health',      [BenchHandler::class, 'health']);
$app->get('/hello',       [BenchHandler::class, 'hello']);
$app->post('/echo',       [BenchHandler::class, 'echo']);
$app->get('/users/{id}',  [BenchHandler::class, 'user']);

// App::run() auto-detects Swoole (PHP_SAPI === 'cli' + swoole extension loaded)
// and enters the Swoole HTTP server event loop.
$app->run();

