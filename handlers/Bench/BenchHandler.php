<?php

declare(strict_types=1);

namespace Handlers\Bench;

use Quill\Request;

/**
 * Static handler class for the Quill benchmark suite.
 *
 * Using array-style handlers (['BenchHandler', 'hello']) instead of closures
 * provides two performance benefits over inline closures:
 *
 *  1. Boot-time param map is keyed by a stable "Class::method" string rather
 *     than spl_object_id(), which is cheaper to look up in the param cache.
 *  2. The JIT compiler can inline / devirtualise static method calls more
 *     aggressively than anonymous closure invocations.
 *
 * Routes
 * ──────
 * GET  /health      → {"status":"ok"}                     (readiness probe)
 * GET  /hello       → {"message":"hello","status":"ok"}   (simple GET, no I/O)
 * POST /echo        → echoes the JSON body back            (JSON decode + encode)
 * GET  /users/{id}  → {"id":<int>}                        (path param)
 */
class BenchHandler
{
    public static function health(): array
    {
        return ['status' => 'ok'];
    }

    public static function hello(): array
    {
        return ['message' => 'hello', 'status' => 'ok'];
    }

    /**
     * Echo the request body back as-is.
     * The param cache built at boot time knows $r is a Request — zero reflection
     * on the hot path.
     */
    public static function echo(Request $r): array
    {
        return $r->json();
    }

    /**
     * Return the path-parameter {id} as an integer.
     * The param cache records type=int so RouteMatch casts it without reflection.
     */
    public static function user(int $id): array
    {
        return ['id' => $id];
    }
}

