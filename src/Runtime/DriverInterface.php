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
    public function allocateResponseBuffer(int $size): mixed;

    /**
     * Pre-bind the TCP port before forking.
     */
    public function prebind(int $port): int;

    /**
     * Start listening on the given port.
     */
    public function listen(mixed $routerHandle, mixed $validatorHandle, int $port, int $workerThreads, int $maxQueue): int;

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

    /**
     * Pre-register a static response for a route handler with the Rust engine.
     * After calling this, Rust will serve all matching requests for this handler
     * directly — bypassing the PHP polling bridge entirely.
     *
     * @param int    $handlerId    The numeric handler ID from the route manifest.
     * @param string $responseJson Full response JSON: {"status":200,"headers":{...},"body":"..."}.
     */
    public function preloadResponse(int $handlerId, string $responseJson): void;


    /**
     * Dispatch a request directly to the core (Sync mode).
     */
    public function dispatch(
        mixed $routerHandle,
        mixed $validatorHandle,
        string $method,
        string $path,
        string $body,
        mixed $outBuf,
        int $outMax
    ): int;

    /**
     * Get JSON metrics from the native engine.
     */
    public function stats(): string;

    /**
     * Signal the native engine to drain the request queue for graceful shutdown.
     */
    public function drain(int $timeoutMs = 0): void;

    /**
     * Specify the destination for native-layer logs.
     */
    public function setLogFile(string $path): void;
}
