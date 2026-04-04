<?php

declare(strict_types=1);

namespace Quill\Contracts;

interface StorageInterface
{
    /** Get a value by key. Returns null when the key does not exist. */
    public function get(string $key): ?string;

    /** Store a value with an optional TTL in seconds. 0 = no expiration. */
    public function set(string $key, string $value, int $ttl = 0): void;

    /** Delete a key. No error if key doesn't exist. */
    public function delete(string $key): void;

    /** Remove all keys. */
    public function reset(): void;

    /** Close the connection / release resources. */
    public function close(): void;
}
