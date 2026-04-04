<?php

declare(strict_types=1);

namespace Quill\Middleware;

use Quill\Request;
use Quill\HttpResponse;

/**
 * Defensive HTTP headers middleware.
 * Implements security best practices by default.
 */
class SecurityHeaders
{
    private array $headers;

    /**
     * @param array{
     *   hsts_max_age?: int,
     *   csp?: string,
     *   frame_options?: 'DENY'|'SAMEORIGIN',
     *   referrer_policy?: string
     * } $config
     */
    public function __construct(array $config = [])
    {
        $hstsAge = $config['hsts_max_age'] ?? 31536000;
        $csp     = $config['csp'] ?? "default-src 'none'; frame-ancestors 'none';";
        $frame   = $config['frame_options'] ?? 'SAMEORIGIN';
        $referrer = $config['referrer_policy'] ?? 'strict-origin-when-cross-origin';

        $this->headers = [
            'Strict-Transport-Security' => "max-age={$hstsAge}; includeSubDomains; preload",
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => $frame,
            'X-XSS-Protection'          => '1; mode=block',
            'Referrer-Policy'           => $referrer,
            'Content-Security-Policy'   => $csp,
        ];
    }

    public function __invoke(Request $request, callable $next): mixed
    {
        return $this->handle($request, $next);
    }

    /**
     * Middleware entry point.
     */
    public function handle(Request $request, callable $next): mixed
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                header("{$name}: {$value}", false); // false to avoid overwriting existing
            }
            
            // Also add to HttpResponse if it's our native response object
            if ($response instanceof HttpResponse) {
                if (!isset($response->headers[$name])) {
                    $response->header($name, $value);
                }
            }
        }

        return $response;
    }
}
