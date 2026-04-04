<?php

declare(strict_types=1);

namespace Quill\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Basic file-based cache for environments without in-memory stores.
 */
class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFile($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if (!$content) return $default;

        $data = unserialize($content);
        if (!is_array($data) || !isset($data['e'], $data['v'])) {
             return $default;
        }

        if ($data['e'] > 0 && time() > $data['e']) {
            $this->delete($key);
            return $default;
        }

        return $data['v'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $expireAt = $this->resolveExpireAt($ttl);
        $data = serialize(['v' => $value, 'e' => $expireAt]);
        return file_put_contents($this->getFile($key), $data) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->directory . '*');
        if ($files === false) return true;
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
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

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) $success = false;
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function getFile(string $key): string
    {
        return $this->directory . md5($key) . '.cache';
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
