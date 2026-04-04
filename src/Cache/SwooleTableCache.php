<?php

declare(strict_types=1);

namespace Quill\Cache;

use Psr\SimpleCache\CacheInterface;
use Swoole\Table;

/**
 * Ultra-high-performance shared memory cache using Swoole Table.
 * Note: Table size and column sizes must be defined at boot.
 */
class SwooleTableCache implements CacheInterface
{
    private Table $table;

    /**
     * @param int $size Maximum number of rows (must be power of 2)
     * @param int $valueLength Maximum length of serialized values in bytes
     */
    public function __construct(int $size = 1024, int $valueLength = 2048)
    {
        $this->table = new Table($size);
        $this->table->column('v', Table::TYPE_STRING, $valueLength);
        $this->table->column('e', Table::TYPE_INT, 8); // expire_at
        $this->table->create();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->table->get($key);
        if (!$row) {
            return $default;
        }

        if ($row['e'] > 0 && time() > $row['e']) {
            $this->table->del($key);
            return $default;
        }

        return unserialize($row['v']);
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $expireAt = $this->resolveExpireAt($ttl);
        return $this->table->set($key, [
            'v' => serialize($value),
            'e' => $expireAt,
        ]);
    }

    public function delete(string $key): bool
    {
        return $this->table->del($key);
    }

    public function clear(): bool
    {
        foreach ($this->table as $key => $row) {
            $this->table->del($key);
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->table->del($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        $row = $this->table->get($key);
        if (!$row) return false;
        
        if ($row['e'] > 0 && time() > $row['e']) {
            $this->table->del($key);
            return false;
        }
        
        return true;
    }

    private function resolveExpireAt(\DateInterval|int|null $ttl): int
    {
        if ($ttl === null) return 0;
        if ($ttl instanceof \DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp();
        }
        return time() + $ttl;
    }
}
