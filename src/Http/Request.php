<?php

declare(strict_types=1);

namespace Quill\Http;

/**
 * Request wrapper for Quill.
 * Directly wraps $_SERVER, $_GET, and php://input for zero allocation.
 */
class Request
{
    /** @var array<string, string> */
    private array $pathVars = [];
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;
    private ?string $rawInputOverride = null;
    private ?string $methodOverride = null;
    private ?string $pathOverride = null;
    /** @var array<string, mixed> */
    private array $context = [];

    public function method(): string
    {
        $m = $this->methodOverride ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return is_string($m) ? $m : 'GET';
    }

    public function set(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    /**
     * Best-effort client IP address.
     * Respects X-Forwarded-For when set (reverse-proxy environments).
     */
    public function ip(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $xffString = is_string($xff) ? $xff : '';
            return trim(explode(',', $xffString)[0]);
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return is_string($remote) ? $remote : '127.0.0.1';
    }

    /**
     * Retrieve a request header value (case-insensitive).
     * Returns null when the header is absent.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * HTTP protocol string, e.g. "HTTP/1.1" or "HTTP/2.0".
     */
    public function protocol(): string
    {
        $proto = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        return is_string($proto) ? $proto : 'HTTP/1.1';
    }

    public function path(): string
    {
        if ($this->pathOverride !== null) {
            return $this->pathOverride;
        }

        $uriRaw = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = is_string($uriRaw) ? $uriRaw : '/';
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return strpos($uri, '%') !== false ? rawurldecode($uri) : $uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * @param array<string, string> $vars
     */
    public function setPathVars(array $vars): void
    {
        $this->pathVars = $vars;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->pathVars[$key] ?? $default;
    }

    /**
     * Override the raw input body.
     */
    public function withInput(string $raw): static
    {
        $clone = clone $this;
        $clone->rawInputOverride = $raw;
        $clone->jsonBody = null; 
        return $clone;
    }

    /**
     * Override the HTTP method (for testing).
     */
    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->methodOverride = strtoupper($method);
        return $clone;
    }

    /**
     * Override the URI path (for testing).
     */
    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->pathOverride = $path;
        return $clone;
    }

    /**
     * Get the raw request body.
     */
    public function input(): string
    {
        return $this->rawInputOverride ?? (string)file_get_contents('php://input');
    }

    /**
     * Parse and cache the JSON input body.
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $input = $this->input();

        if (empty($input)) {
            return $this->jsonBody = [];
        }

        if (strlen($input) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Payload too large.');
        }

        try {
            $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Invalid JSON body.');
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON body must be an object or array.');
        }

        /** @var array<string, mixed> $decoded */
        return $this->jsonBody = $decoded;
    }
}
