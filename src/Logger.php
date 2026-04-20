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
class Logger implements \Psr\Log\LoggerInterface
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

    private const COLORS = [
        'reset'   => "\033[0m",
        'bold'    => "\033[1m",
        'dim'     => "\033[2m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'red'     => "\033[31m",
        'cyan'    => "\033[36m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
    ];

    /** @var resource|false */
    private $handle = false;
    private bool $isTty = false;

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
        $this->isTty = $this->checkTty();
    }

    private function checkTty(): bool
    {
        if ($this->json) return false;
        if ($this->destination === 'php://stdout' || $this->destination === 'php://stderr') {
            return stream_isatty($this->handle ?: STDOUT);
        }
        return false;
    }

    private function color(string $text, string $color): string
    {
        if (!$this->isTty) return $text;
        return (self::COLORS[$color] ?? '') . $text . self::COLORS['reset'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Levelled logging
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $numericLevel = is_int($level) ? $level : match(is_scalar($level) ? (string)$level : 'info') {
            'debug'     => self::DEBUG,
            'info'      => self::INFO,
            'notice'    => self::INFO,
            'warning'   => self::WARNING,
            'error'     => self::ERROR,
            'critical'  => self::CRITICAL,
            'alert'     => self::CRITICAL,
            'emergency' => self::CRITICAL,
            default     => self::INFO,
        };

        if ($numericLevel < $this->minLevel || $this->handle === false) {
            return;
        }

        $ts        = date('Y-m-d\TH:i:sP');
        $levelName = self::LEVEL_NAMES[$numericLevel] ?? (is_scalar($level) ? strtoupper((string)$level) : 'INFO');

        if ($this->json) {
            $entry = ['ts' => $ts, 'level' => $levelName, 'msg' => (string)$message];
            if ($context) {
                $entry['ctx'] = $context;
            }
            $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $ctx  = $context
                ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '';
            
            $color = match($numericLevel) {
                self::DEBUG    => 'dim',
                self::INFO     => 'cyan',
                self::WARNING  => 'yellow',
                self::ERROR, 
                self::CRITICAL => 'red',
                default        => 'reset',
            };

            $line = sprintf(
                "[%s] %s: %s%s\n",
                $this->color($ts, 'dim'),
                $this->color($levelName, $color),
                (string)$message,
                $ctx
            );
        }

        fwrite($this->handle, $line);
    }

    public function emergency(string|\Stringable $message, array $context = []): void { $this->log(self::CRITICAL, $message, $context); }
    public function alert(string|\Stringable $message, array $context = []): void     { $this->log(self::CRITICAL, $message, $context); }
    public function critical(string|\Stringable $message, array $context = []): void  { $this->log(self::CRITICAL, $message, $context); }
    public function error(string|\Stringable $message, array $context = []): void     { $this->log(self::ERROR, $message, $context); }
    public function warning(string|\Stringable $message, array $context = []): void   { $this->log(self::WARNING, $message, $context); }
    public function notice(string|\Stringable $message, array $context = []): void    { $this->log(self::INFO, $message, $context); }
    public function info(string|\Stringable $message, array $context = []): void      { $this->log(self::INFO, $message, $context); }
    public function debug(string|\Stringable $message, array $context = []): void     { $this->log(self::DEBUG, $message, $context); }

    // ──────────────────────────────────────────────────────────────────────────
    // Access logging  (Apache Combined Log Format extension)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write one access-log line after a request is completed.
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
        } elseif ($this->isTty) {
            // High-fidelity modern CLI format
            $methodColor = match($method) {
                'GET'    => 'green',
                'POST'   => 'cyan',
                'PUT'    => 'yellow',
                'DELETE' => 'red',
                default  => 'magenta',
            };

            $statusColor = match(true) {
                $status >= 500 => 'red',
                $status >= 400 => 'yellow',
                $status >= 300 => 'cyan',
                $status >= 200 => 'green',
                default        => 'reset',
            };

            $durStr = ($durationMs < 1.0)
                ? round($durationMs * 1000) . "µs"
                : sprintf("%.3fms", $durationMs);

            $line = sprintf(
                " %s  %s %-30s %s %s\n",
                $this->color(date('H:i:s'), 'dim'),
                $this->color(sprintf("%-6s", $method), $methodColor),
                $this->color($path, 'bold'),
                $this->color((string)$status, $statusColor),
                $this->color($durStr, 'dim')
            );
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

