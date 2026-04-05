<?php

declare(strict_types=1);

namespace Quill\Runtime;

/**
 * Interface for the Quill Native Core driver.
 * Decouples the PHP framework from direct FFI calls for testing and portability.
 */
interface DriverInterface
{
    /**
     * Convert an FFI buffer to a PHP string.
     */
    public function getString(mixed $buffer): string;

    /**
     * Allocate buffers for the polling loop.
     */
    public function allocateIdBuffer(): mixed;
    public function allocateHandlerIdBuffer(): mixed;
    public function allocateParamsBuffer(int $size): mixed;
    public function allocateDtoBuffer(int $size): mixed;

    /**
     * Pre-bind the TCP port before forking.
     */
    public function prebind(int $port): int;

    /**
     * Start listening on the given port.
     */
    public function listen(mixed $routerHandle, mixed $validatorHandle, int $port): int;

    /**
     * Poll for the next incoming request.
     */
    public function poll(
        mixed $idBuf, 
        mixed $handlerIdBuf, 
        mixed $paramsBuf, 
        int $paramsMax, 
        mixed $dtoBuf, 
        int $dtoMax
    ): int;

    /**
     * Send a response back to the client.
     */
    public function respond(int $id, string $json): int;
}
