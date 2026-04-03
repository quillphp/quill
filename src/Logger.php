<?php

declare(strict_types=1);

namespace Quill;

/**
 * High-speed structured logger for Quill.
 *
 * Features
 * ────────
 * • Log levels  : DEBUG → INFO → WARNING → ERROR → CRITICAL
 * • Formats     : plain text (default) or NDJSON (log shippers / ELK)
 * • Access log  : Apache Combined Log Format or NDJSON
 * • Transport   : buffered fwrite() — one syscall per line, no blocking
 * • Destinations: any writable path, "php://stderr", "php://stdout"
 *
 * Usage
 * ──────
 * $log = new Logger('/var/log/quill/app.log');
 * $log = new Logger('php://stderr', Logger::WARNING);
 * $log = new Logger('/var/log/quill/app.log', Logger::DEBUG, json: true);
 *
 * // App integration (auto-created from config):
 * new App(['logger' => $log]);
 */
class Logger
{
    public const DEBUG    = 10;
    public const INFO     = 20;
    public const WARNING  = 30;
    public const ERROR    = 40;
    public const CRITICAL = 50;

    private const LEVEL_NAMES = [
        self::DEBUG    => 'DEBUG',
        self::INFO     => 'INFO',
        self::WARNING  => 'WARNING',
        self::ERROR    => 'ERROR',
        self::CRITICAL => 'CRITICAL',
    ];

    /** @var resource|false */
    private $handle = false;

    /**
     * @param string $destination  File path, "php://stderr", or "php://stdout".
     * @param int    $minLevel     Minimum level to emit (use Logger::* constants).
     * @param bool   $json         Emit NDJSON instead of plain-text lines.
     */
    public function __construct(
        private readonly string $destination = 'php://stderr',
        private readonly int $minLevel = self::DEBUG,
        private readonly bool $json = false
    ) {
        $this->open();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Levelled logging
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     */
    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLevel || $this->handle === false) {
            return;
        }

        $ts        = date('Y-m-d\TH:i:sP');
        $levelName = self::LEVEL_NAMES[$level] ?? 'UNKNOWN';

        if ($this->json) {
            $entry = ['ts' => $ts, 'level' => $levelName, 'msg' => $message];
            if ($context) {
                $entry['ctx'] = $context;
            }
            $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $ctx  = $context
                ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '';
            $line = "[$ts] $levelName: $message$ctx\n";
        }

        fwrite($this->handle, $line);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Access logging  (Apache Combined Log Format extension)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write one access-log line after a request is completed.
     *
     * Plain-text example (Apache CLF + duration):
     *   127.0.0.1 - - [03/Apr/2026:12:00:00 +0000] "GET /hello HTTP/1.1" 200 34 "-" "wrk/4.2.0" 1.234ms
     *
     * JSON example:
     *   {"ts":"2026-04-03T12:00:00+00:00","type":"access","ip":"127.0.0.1","method":"GET","path":"/hello","proto":"HTTP/1.1","status":200,"bytes":34,"referer":"-","ua":"wrk/4.2.0","ms":1.234}
     */
    public function access(
        string $ip,
        string $method,
        string $path,
        string $protocol,
        int    $status,
        int    $bytes,
        string $referer   = '-',
        string $userAgent = '-',
        float  $durationMs = 0.0
    ): void {
        if ($this->handle === false) {
            return;
        }

        if ($this->json) {
            $line = json_encode([
                'ts'      => date('Y-m-d\TH:i:sP'),
                'type'    => 'access',
                'ip'      => $ip,
                'method'  => $method,
                'path'    => $path,
                'proto'   => $protocol,
                'status'  => $status,
                'bytes'   => $bytes,
                'referer' => $referer,
                'ua'      => $userAgent,
                'ms'      => round($durationMs, 3),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $ts   = date('d/M/Y:H:i:s O');
            $line = sprintf(
                "%s - - [%s] \"%s %s %s\" %d %d \"%s\" \"%s\" %.3fms\n",
                $ip, $ts, $method, $path, $protocol,
                $status, $bytes, $referer, $userAgent, $durationMs
            );
        }

        fwrite($this->handle, $line);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal
    // ──────────────────────────────────────────────────────────────────────────

    private function open(): void
    {
        if ($this->destination === 'php://stderr' || $this->destination === 'php://stdout') {
            $this->handle = fopen($this->destination, 'w');
            return;
        }

        $dir = dirname($this->destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->handle = fopen($this->destination, 'a');
    }

    public function __destruct()
    {
        if (
            $this->handle !== false
            && $this->destination !== 'php://stderr'
            && $this->destination !== 'php://stdout'
        ) {
            fclose($this->handle);
        }
    }
}

