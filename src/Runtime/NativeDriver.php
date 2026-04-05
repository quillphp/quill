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

    public function prebind(int $port): int
    {
        try {
            /** @phpstan-ignore-next-line */
            return $this->ffi->quill_server_prebind($port);
        } catch (\FFI\Exception) {
            return -1;
        }
    }

    public function listen(mixed $routerHandle, mixed $validatorHandle, int $port): int
    {
        /** @phpstan-ignore-next-line */
        return $this->ffi->quill_server_listen($routerHandle, $validatorHandle, $port);
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
}
