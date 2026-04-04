<?php

declare(strict_types=1);

namespace Quill;

/**
 * Robust CORS middleware for Quill.
 * Supports multiple origins, regex patterns, and preflight optimization.
 */
class Cors
{
    /** @var array<string> */
    private array $origins;
    /** @var array<string> */
    private array $methods;
    /** @var array<string> */
    private array $headers;
    /** @var array<string> */
    private array $exposedHeaders;
    private int $maxAge;
    private bool $credentials;

    /**
     * @param array{
     *   origins?: array<string>,
     *   methods?: array<string>,
     *   headers?: array<string>,
     *   exposed_headers?: array<string>,
     *   max_age?: int,
     *   credentials?: bool
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->origins        = $options['origins'] ?? ['*'];
        $this->methods        = $options['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->headers        = $options['headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->exposedHeaders = $options['exposed_headers'] ?? [];
        $this->maxAge         = $options['max_age'] ?? 86400;
        $this->credentials    = $options['credentials'] ?? false;
    }

    /**
     * Static factory for backward compatibility and quick usage.
     * @param array<string, mixed> $options
     * @return callable(Request, callable): mixed
     */
    public static function middleware(array $options = []): callable
    {
        $instance = new self($options);
        return [$instance, 'handle'];
    }

    /**
     * Middleware entry point.
     */
    public function handle(Request $request, callable $next): mixed
    {
        $origin = $request->header('Origin');
        $allowOrigin = $origin ? $this->resolveAllowedOrigin($origin) : null;

        if ($allowOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->methods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->headers));
            header('Access-Control-Max-Age: ' . $this->maxAge);

            if ($this->credentials) {
                header('Access-Control-Allow-Credentials: true');
            }

            if (!empty($this->exposedHeaders)) {
                header('Access-Control-Expose-Headers: ' . implode(', ', $this->exposedHeaders));
            }
        }

        // Short-circuit OPTIONS preflight
        if ($request->method() === 'OPTIONS') {
            if (!headers_sent()) {
                http_response_code(204);
            }
            return [];
        }

        return $next($request);
    }

    /**
     * Determine if the requested origin matches the configured allowed origins.
     */
    private function resolveAllowedOrigin(string $origin): ?string
    {
        if (in_array('*', $this->origins, true)) {
            return '*';
        }

        foreach ($this->origins as $allowed) {
            if ($allowed === $origin) {
                return $origin;
            }
            // Support basic wildcards/regex if the string starts with #
            if (str_starts_with($allowed, '#') && preg_match($allowed, $origin)) {
                return $origin;
            }
        }

        return null;
    }
}
