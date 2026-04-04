<?php

declare(strict_types=1);

namespace Quill;

/**
 * Lightweight response value object for handlers.
 */
class HttpResponse
{
    /**
     * @param mixed $data Response data to be JSON encoded
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional response headers
     */
    public function __construct(
        public readonly mixed $data,
        public readonly int $status = 200,
        public array $headers = [],
    ) {}

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public static function created(mixed $data): self
    {
        return new self($data, 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public static function accepted(mixed $data): self
    {
        return new self($data, 202);
    }
}
