<?php

namespace Quill\Runtime;

/**
 * SocketServer — A Pure PHP fallback for CI/CD environments where FFI::callback is disabled.
 * 
 * This server uses stream_socket_server and stream_select to provide a high-performance
 * I/O loop while still utilizing the Native Rust Core for request dispatching.
 */
class SocketServer
{
    private mixed $socket;
    private int $maxResponseSize = 65536;

    private DriverInterface $driver;

    public function __construct(
        private readonly mixed $router,
        private readonly mixed $validator,
        private readonly int $port,
        ?DriverInterface $driver = null
    ) {
        $this->driver = $driver ?: Runtime::getDriver();
    }

    /**
     * Start the stream-based server.
     */
    public function start(): void
    {
        $address = "tcp://0.0.0.0:{$this->port}";
        $this->socket = @stream_socket_server($address, $errno, $errstr);

        if (!$this->socket) {
            throw new \RuntimeException("Could not bind to {$address}: {$errstr} ({$errno})");
        }

        @stream_set_blocking($this->socket, false);

        echo "  > SocketServer (Fallback) listening on port {$this->port}...\n";

        $sockets = [(int)$this->socket => $this->socket];

        while (true) {
            $read = $sockets;
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($read as $s) {
                if ($s === $this->socket) {
                    $conn = @stream_socket_accept($this->socket);
                    if ($conn) {
                        @stream_set_blocking($conn, false);
                        $sockets[(int)$conn] = $conn;
                    }
                } else {
                    $this->handle($s, $sockets);
                }
            }
        }
    }

    /**
     * Handle an individual request.
     * @param resource $conn
     * @param array<int, resource> $sockets
     */
    private function handle($conn, array &$sockets): void
    {
        $buffer = @fread($conn, 8192);

        if (!$buffer) {
            @fclose($conn);
            unset($sockets[(int)$conn]);
            return;
        }

        // 1. Primitive HTTP Parsing (Optimized for Benchmark CI)
        $lines = explode("\r\n", $buffer);
        $firstLine = explode(' ', $lines[0]);
        
        if (count($firstLine) < 3) {
            @fclose($conn);
            unset($sockets[(int)$conn]);
            return;
        }

        $method = $firstLine[0];
        $path = $firstLine[1];
        
        // Find body if any
        $body = "";
        $emptyLineFound = false;
        foreach ($lines as $line) {
            if ($emptyLineFound) {
                $body .= $line . "\r\n";
            }
            if ($line === "") {
                $emptyLineFound = true;
            }
        }

        // 2. Native Dispatch (This doesn't require FFI::callback)
        $outBuf = $this->driver->allocateResponseBuffer($this->maxResponseSize);
        
        $len = $this->driver->dispatch(
            $this->router,
            $this->validator,
            $method,
            $path,
            $body,
            $outBuf,
            $this->maxResponseSize
        );

        if ($len < 0) {
            $response = "HTTP/1.1 500 Internal Server Error\r\nContent-Type: text/plain\r\nConnection: close\r\n\r\nNative Dispatch Error";
        } else {
            $jsonResponse = $this->driver->getString($outBuf);
            $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($jsonResponse) . "\r\nConnection: close\r\n\r\n" . $jsonResponse;
        }

        // 3. Write and Close
        @fwrite($conn, $response);
        @fclose($conn);
        unset($sockets[(int)$conn]);
    }
}
