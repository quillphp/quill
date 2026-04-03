<?php

declare(strict_types=1);

/**
 * OPcache preload script for QuillPHP.
 *
 * Wire this up in php.ini (or a conf.d snippet) BEFORE starting PHP-FPM /
 * FrankenPHP / Swoole workers:
 *
 *   opcache.preload      = /app/scripts/preload.php
 *   opcache.preload_user = www-data   ; or root inside Docker
 *
 * What it does
 * ────────────
 * Compiles and caches all Quill framework files + the FastRoute dispatcher
 * into the shared OPcache segment at worker-startup time.  Subsequent
 * requests find every class already compiled — zero file I/O, zero bytecode
 * compilation per request.
 *
 * For FrankenPHP (ZTS PHP, JIT disabled):
 *   Eliminates file-stat + bytecode-parse overhead on the first request
 *   of each worker.  Combined with validate_timestamps=0, files are never
 *   re-checked during the process lifetime.
 *
 * For Swoole (NTS PHP, JIT tracing enabled):
 *   The JIT compiler can trace preloaded code immediately on the first
 *   warm request, without waiting for the per-class compile threshold.
 */

// Ensure the autoloader is available so class maps are built correctly.
require_once __DIR__ . '/../../vendor/autoload.php';

// ── Quill framework core ─────────────────────────────────────────────────────
$quillSrc = [
    'App',
    'Router',
    'RouteMatch',
    'Request',
    'Response',
    'Pipeline',
    'HttpResponse',
    'HtmlResponse',
    'ValidationException',
    'Validator',
    'DTO',
    'Logger',
    'Cors',
    'OpenApi',
];

foreach ($quillSrc as $class) {
    $file = __DIR__ . '/../../src/' . $class . '.php';
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}

// ── Quill attributes ─────────────────────────────────────────────────────────
$attrDir = __DIR__ . '/../../attributes/';
if (is_dir($attrDir)) {
    foreach (glob($attrDir . '*.php') ?: [] as $file) {
        opcache_compile_file($file);
    }
}

// ── FastRoute dispatcher (always on the hot path) ────────────────────────────
$fastRouteFiles = [
    'src/Dispatcher.php',
    'src/Dispatcher/GroupCountBased.php',
    'src/Dispatcher/RegexBasedAbstract.php',
    'src/Dispatcher/MarkBased.php',
    'src/Dispatcher/CharCountBased.php',
    'src/RouteCollector.php',
    'src/RouteParser.php',
    'src/RouteParser/Std.php',
    'src/DataGenerator.php',
    'src/DataGenerator/GroupCountBased.php',
    'src/DataGenerator/RegexBasedAbstract.php',
    'src/DataGenerator/MarkBased.php',
    'src/DataGenerator/CharCountBased.php',
    'src/functions.php',
];

$fastRouteBase = __DIR__ . '/../../vendor/nikic/fast-route/';
foreach ($fastRouteFiles as $rel) {
    $file = $fastRouteBase . $rel;
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}

// ── Benchmark handlers (only present in benchmark images) ────────────────────
$benchHandler = __DIR__ . '/../../handlers/BenchHandler.php';
if (file_exists($benchHandler)) {
    opcache_compile_file($benchHandler);
}

