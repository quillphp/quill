<?php

declare(strict_types=1);

namespace Quill\Middleware;

/**
 * APCu-backed rate limit storage provider.
 * High-performance shared storage for single-node deployments.
 */
class APCuRateLimitStorage implements RateLimitStorageInterface
{
    /**
     * @param string $prefix Prefix to avoid key collisions in APCu
     */
    public function __construct(private string $prefix = 'quill_ratelimit:') {}

    public function increment(string $key, int $window): int
    {
        $fullKey = $this->prefix . $key;
        
        // Initial set if not exists
        if (!apcu_exists($fullKey)) {
            apcu_add($fullKey, 1, $window);
            return 1;
        }

        // Atomically increment the value
        $count = apcu_inc($fullKey, 1, $success, $window);
        
        // Return current count, or 1 if increment failed
        return is_int($count) ? $count : 1;
    }

    public function reset(string $key): void
    {
        apcu_delete($this->prefix . $key);
    }
}
