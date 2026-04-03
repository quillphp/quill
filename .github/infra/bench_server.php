<?php

declare(strict_types=1);

/**
 * Quill benchmark server entry point.
 *
 * Start with the PHP built-in server:
 *   php -S 127.0.0.1:8765 scripts/bench_server.php
 *
 * Or with FrankenPHP (recommended for prod-grade numbers):
 *   frankenphp php-server -r scripts/bench_server.php --listen :8765
 *
 * Routes
 * ──────
 * GET  /health       → {"status":"ok"}              (readiness probe)
 * GET  /hello        → {"message":"hello"}          (simple GET, no I/O)
 * POST /echo         → echoes the JSON body back     (JSON decode + encode)
 * GET  /users/{id}   → {"id": <int>}                (path param)
 *
 * Using BenchHandler (array handlers) instead of closures gives the JIT
 * more predictable call-sites and avoids spl_object_id key generation.
 */

// Let the built-in server serve real static files untouched.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . '/../public' . ($_SERVER['REQUEST_URI'] ?? '/');
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

use Handlers\BenchHandler;
use Quill\App;

$app = new App([
    // No disk route cache in single-process built-in server mode
    // (the process lives forever so the in-memory cache is warm after boot).
    'route_cache' => false,
    // Logging disabled by default for pure perf measurement.
    // Enable for realistic prod testing: 'logger' => 'php://stderr'
]);

// ── Routes ──────────────────────────────────────────────────────────────────

$app->get('/health',      [BenchHandler::class, 'health']);
$app->get('/hello',       [BenchHandler::class, 'hello']);
$app->post('/echo',       [BenchHandler::class, 'echo']);
$app->get('/users/{id}',  [BenchHandler::class, 'user']);

// ── Run ─────────────────────────────────────────────────────────────────────

$app->run();

