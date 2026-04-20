<?php

declare(strict_types=1);

namespace Quill\Runtime;

/**
 * FFI-based implementation of the Quill Runtime Driver.
 */
class NativeDriver implements DriverInterface
{
    private \FFI $ffi;

    public function __construct(\FFI $ffi)
    {
        $this->ffi = $ffi;
    }

    public function getString(mixed $buffer): string
    {
        /** @var \FFI\CData $buffer */
        return \FFI::string($buffer);
    }

    public function allocateIdBuffer(): mixed
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->new('uint32_t[1]');
    }

    public function allocateHandlerIdBuffer(): mixed
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->new('uint32_t[1]');
    }

    public function allocateParamsBuffer(int $size): mixed
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->new("char[{$size}]");
    }

    public function allocateDtoBuffer(int $size): mixed
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->new("char[{$size}]");
    }

    public function allocateResponseBuffer(int $size): mixed
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->new("char[{$size}]");
    }

    public function prebind(int $port): int
    {
        try {
            /** @phpstan-ignore-next-line */
            return $this->ffi->quill_server_prebind($port);
        } catch (\FFI\Exception) {
            return -1;
        }
    }

    public function listen(mixed $routerHandle, mixed $validatorHandle, int $port, int $workerThreads, int $maxQueue): int
    {
        /** @phpstan-ignore-next-line */
        return (int)$this->ffi->quill_server_listen($routerHandle, $validatorHandle, $port, $workerThreads, $maxQueue);
    }

    public function poll(
        mixed $idBuf,
        mixed $handlerIdBuf,
        mixed $paramsBuf,
        int $paramsMax,
        mixed $dtoBuf,
        int $dtoMax
    ): int {
        /** @phpstan-ignore-next-line */
        return $this->ffi->quill_server_poll($idBuf, $handlerIdBuf, $paramsBuf, $paramsMax, $dtoBuf, $dtoMax);
    }

    public function respond(int $id, string $json): int
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->quill_server_respond($id, $json, strlen($json));
    }

    public function preloadResponse(int $handlerId, string $responseJson): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $this->ffi->quill_route_preload($handlerId, $responseJson, strlen($responseJson));
        } catch (\FFI\Exception) {
            // Non-fatal: fall back to the PHP bridge for this handler.
        }
    }


    public function dispatch(
        mixed $routerHandle,
        mixed $validatorHandle,
        string $method,
        string $path,
        string $body,
        mixed $outBuf,
        int $outMax
    ): int {
        /** @phpstan-ignore-next-line */
        return $this->ffi->quill_router_dispatch(
            $routerHandle,
            $validatorHandle,
            $method,
            strlen($method),
            $path,
            strlen($path),
            $body,
            strlen($body),
            $outBuf,
            $outMax
        );
    }

    public function stats(): string
    {
        /** @phpstan-ignore-next-line */
        $ptr = $this->ffi->quill_server_stats();
        if ($ptr === null) {
            return '{}';
        }
        $json = \FFI::string($ptr);
        /** @phpstan-ignore-next-line */
        $this->ffi->quill_server_stats_free($ptr);
        return $json;
    }

    public function drain(int $timeoutMs = 0): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $this->ffi->quill_server_drain($timeoutMs);
        } catch (\Throwable) {
            // FFI drain failed or not supported in this binary version
        }
    }

    public function setLogFile(string $path): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $this->ffi->quill_server_set_log_file($path);
        } catch (\Throwable) {
            // FFI call failed or not supported in this binary version
        }
    }
}
