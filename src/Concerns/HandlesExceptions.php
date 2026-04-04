<?php

declare(strict_types=1);

namespace Quill\Concerns;

use Quill\Response;
use Quill\ValidationException;

/**
 * Exception handling logic for the App class.
 */
trait HandlesExceptions
{
    /**
     * Standard error handling based on ENV.
     */
    protected function handleException(\Throwable $e, Response $response): void
    {
        if ($e instanceof ValidationException) {
            $response->json([
                'status' => 422,
                'error'  => 'Validation Failed',
                'errors' => $e->getErrors(),
            ], 422);
            return;
        }

        $status = ($e instanceof \InvalidArgumentException) ? 400 : 500;
        
        if ($this->config['debug'] || $this->config['env'] === 'dev') {
            $response->json([
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ], $status);
        } else {
            $response->json([
                'status' => $status,
                'error' => $status === 400 ? $e->getMessage() : 'Internal Server Error',
                'status_code' => $status,
            ], $status);
        }
    }
}
