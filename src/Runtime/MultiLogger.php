<?php

declare(strict_types=1);

namespace Quill\Runtime;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Composite logger that broadcasts log entries to multiple LoggerInterface instances.
 * Also supports the Quill-specific access() method for request logging.
 */
class MultiLogger implements LoggerInterface
{
    /**
     * @param LoggerInterface[] $loggers
     */
    public function __construct(private array $loggers = [])
    {
    }

    public function addLogger(LoggerInterface $logger): void
    {
        $this->loggers[] = $logger;
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->emergency($message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->alert($message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->critical($message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->error($message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->warning($message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->notice($message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->info($message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->debug($message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) $logger->log($level, $message, $context);
    }

    /**
     * Broadcast access log entries.
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
        foreach ($this->loggers as $logger) {
            if ($logger instanceof \Quill\Logger) {
                $logger->access($ip, $method, $path, $protocol, $status, $bytes, $referer, $userAgent, $durationMs);
            } elseif (method_exists($logger, 'access')) {
                /** @phpstan-ignore-next-line */
                $logger->access($ip, $method, $path, $protocol, $status, $bytes, $referer, $userAgent, $durationMs);
            }
        }
    }
}
