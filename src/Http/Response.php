<?php

declare(strict_types=1);

namespace Quill\Http;

use Quill\Runtime\Json;

/**
 * Native output strategy for Quill responses.
 * Simple, high-speed, JSON-first.
 */
class Response
{
    /** @var array<string, string> */
    private array $headers = [];
    private int $status = 200;
    private bool $headOnly = false;
    private int $bytesSent = 0;

    /**
     * Suppress the response body (used for HEAD requests).
     */
    public function setHeadOnly(bool $v): void
    {
        $this->headOnly = $v;
    }

    /**
     * The HTTP status code that was set on the last send call.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Total bytes written to output by this response.
     */
    public function getBytesSent(): int
    {
        return $this->bytesSent;
    }

    /**
     * Set a response header.
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send a JSON response.
     */
    public function json(mixed $data, int $status = 200): static
    {
        $this->status = $status;

        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json');
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        } else {
            $this->headers['Content-Type'] = 'application/json';
        }

        if (!$this->headOnly) {
            $body = Json::encode($data);
            $this->bytesSent += strlen($body);
            echo $body;
        }

        return $this;
    }

    /**
     * Send an HTML response.
     */
    public function html(string $content, int $status = 200): static
    {
        $this->status = $status;

        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: text/html');
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        } else {
            $this->headers['Content-Type'] = 'text/html';
        }

        if (!$this->headOnly) {
            $this->bytesSent += strlen($content);
            echo $content;
        }

        return $this;
    }

    /**
     * Send a standard error response.
     */
    public function error(string $message, int $status = 500): static
    {
        return $this->json([
            'status' => $status,
            'error'  => $message,
        ], $status);
    }
}
