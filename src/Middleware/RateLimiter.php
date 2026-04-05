<?php

declare(strict_types=1);

namespace Quill\Middleware;

use Quill\Http\Request;
use Quill\Http\HttpResponse;

/**
 * High-performance window-based Rate Limiter middleware.
 */
class RateLimiter
{
    private RateLimitStorageInterface $storage;
    private int $limit;
    private int $window;
    private ?\Closure $keyResolver;

    /**
     * @param RateLimitStorageInterface $storage     The backend storage to use
     * @param int                       $limit       Maximum number of requests allowed
     * @param int                       $window      Time window in seconds (default: 60)
     * @param \Closure|null             $keyResolver Optional closure to resolve the unique client key
     */
    public function __construct(
        RateLimitStorageInterface $storage,
        int $limit = 60,
        int $window = 60,
        ?\Closure $keyResolver = null
    ) {
        $this->storage     = $storage;
        $this->limit       = $limit;
        $this->window      = $window;
        $this->keyResolver = $keyResolver;
    }

    /**
     * Named factory for in-memory storage (most common).
     */
    public static function withMemory(int $limit = 60, int $window = 60): self
    {
        return new self(new InMemoryRateLimitStorage(), $limit, $window);
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
        // 1. Resolve client key (default: IP)
        $key = $this->keyResolver ? ($this->keyResolver)($request) : $request->ip();
        
        // 2. Map method and path if you want more granular limits, 
        // but for now, we'll keep it simple per-client.
        $storageKey = "rate_limit:{$key}";
        
        // 3. Atomically increment the hit count
        $count = $this->storage->increment($storageKey, $this->window);
        
        // 4. Check if limit exceeded
        if ($count > $this->limit) {
            return new HttpResponse(
                ['status' => 429, 'error' => 'Too Many Requests'],
                429,
                ['Retry-After' => (string)$this->window]
            );
        }

        // 5. Execute downstream handler/middlewares
        $response = $next($request);

        // 6. Add rate-limit information headers to the response (if it's an object)
        if ($response instanceof HttpResponse) {
            $response->header('X-RateLimit-Limit', (string)$this->limit);
            $response->header('X-RateLimit-Remaining', (string)max(0, $this->limit - $count));
            $response->header('X-RateLimit-Reset', (string)(time() + $this->window));
        }

        return $response;
    }
}
