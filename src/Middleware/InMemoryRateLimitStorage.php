<?php

declare(strict_types=1);

namespace Quill\Middleware;

/**
 * In-memory rate limit storage provider.
 * Note: State is lost on process restart and not shared across processes.
 */
class InMemoryRateLimitStorage implements RateLimitStorageInterface
{
    /** @var array<string, array{count: int, expires_at: int}> */
    private array $storage = [];

    public function increment(string $key, int $window): int
    {
        $now = time();

        if (!isset($this->storage[$key]) || $now >= $this->storage[$key]['expires_at']) {
            $this->storage[$key] = [
                'count' => 1,
                'expires_at' => $now + $window,
            ];
            return 1;
        }

        $this->storage[$key]['count']++;
        return $this->storage[$key]['count'];
    }

    public function reset(string $key): void
    {
        unset($this->storage[$key]);
    }
}
