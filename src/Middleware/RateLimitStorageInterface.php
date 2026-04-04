<?php

declare(strict_types=1);

namespace Quill\Middleware;

/**
 * Interface for rate limit storage backends.
 */
interface RateLimitStorageInterface
{
    /**
     * Increment the hit count for a key within a given window.
     * 
     * @param string $key The unique identifier (e.g., "rate_limit:127.0.0.1")
     * @param int $window The time window in seconds
     * @return int The new hit count
     */
    public function increment(string $key, int $window): int;

    /**
     * Reset the hit count for a key.
     */
    public function reset(string $key): void;
}
