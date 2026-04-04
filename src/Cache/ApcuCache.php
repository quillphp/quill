<?php

declare(strict_types=1);

namespace Quill\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * High-performance shared memory cache using APCu.
 */
class ApcuCache implements CacheInterface
{
    private string $prefix;

    public function __construct(string $prefix = 'quill_')
    {
        $this->prefix = $prefix;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $success = false;
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return apcu_store($this->prefix . $key, $value, $this->resolveTtl($ttl));
    }

    public function delete(string $key): bool
    {
        return apcu_delete($this->prefix . $key);
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefix . $key;
        }

        /** @var array<string, mixed> $results */
        $results = apcu_fetch($prefixedKeys);
        $final = [];

        foreach ($keys as $key) {
            $final[$key] = $results[$this->prefix . $key] ?? $default;
        }

        return $final;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $prefixedValues = [];
        $t = $this->resolveTtl($ttl);
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefix . $key] = $value;
        }

        $failed = apcu_store($prefixedValues, null, $t);
        return empty($failed);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefix . $key;
        }

        $failed = apcu_delete($prefixedKeys);
        return empty($failed);
    }

    public function has(string $key): bool
    {
        return apcu_exists($this->prefix . $key);
    }

    private function resolveTtl(\DateInterval|int|null $ttl): int
    {
        if ($ttl instanceof \DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp() - time();
        }
        return (int)($ttl ?? 0);
    }
}
