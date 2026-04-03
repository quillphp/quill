<?php

declare(strict_types=1);

namespace Quill;

/**
 * Built-in CORS middleware factory for Quill.
 *
 * Usage:
 *   $app->use(Cors::middleware([
 *       'origins' => ['https://myapp.com'],
 *       'headers' => ['Content-Type', 'Authorization'],
 *   ]));
 */
class Cors
{
    /**
     * @param array{origins?: array<string>, methods?: array<string>, headers?: array<string>, max_age?: int} $options
     * @return callable(Request, callable): mixed
     */
    public static function middleware(array $options = []): callable
    {
        $origins = $options['origins'] ?? ['*'];
        $methods = $options['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $headers = $options['headers'] ?? ['Content-Type', 'Authorization'];
        $maxAge  = $options['max_age'] ?? 86400;

        return function (Request $request, callable $next) use ($origins, $methods, $headers, $maxAge): mixed {
            $origin      = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowOrigin = in_array('*', $origins, true)
                ? '*'
                : (in_array($origin, $origins, true) ? $origin : '');

            if ($allowOrigin !== '') {
                header('Access-Control-Allow-Origin: ' . $allowOrigin);
                header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
                header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
                header('Access-Control-Max-Age: ' . $maxAge);
            }

            // Short-circuit OPTIONS preflight — no need to invoke the handler.
            if ($request->method() === 'OPTIONS') {
                http_response_code(204);
                return [];
            }

            return $next($request);
        };
    }
}
