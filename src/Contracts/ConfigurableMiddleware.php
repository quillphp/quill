<?php

declare(strict_types=1);

namespace Quill\Contracts;

/** Marker trait for middleware that follows the Config pattern. */
trait ConfigurableMiddleware
{
    /** @return array<string, mixed> */
    abstract protected static function defaults(): array;

    /** 
     * Factory: creates instance with merged config.
     * @param array<string, mixed> $config 
     */
    public static function new(array $config = []): static
    {
        /** @phpstan-ignore-next-line */
        return new static(array_merge(static::defaults(), $config));
    }
}
