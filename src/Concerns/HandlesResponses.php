<?php

declare(strict_types=1);

namespace Quill\Concerns;

use Quill\Response;
use Quill\HtmlResponse;
use Quill\HttpResponse;

/**
 * response sending logic for the App class.
 */
trait HandlesResponses
{
    /**
     * Send the result back to the client using the Response strategy.
     */
    protected function sendResponse(mixed $result, Response $response): void
    {
        if ($result instanceof HtmlResponse) {
            foreach ($result->headers as $name => $value) {
                $response->header($name, $value);
            }
            $response->html($result->html, $result->status);
            return;
        }

        if ($result instanceof HttpResponse) {
            foreach ($result->headers as $name => $value) {
                $response->header($name, $value);
            }
            if ($result->status === 204) {
                $response->setHeadOnly(true);
            }
            $response->json($result->data, $result->status);
            return;
        }

        // Hot path: non-empty result → always 200.
        if (!empty($result)) {
            $response->json($result);
            return;
        }

        $currentCode = http_response_code();
        if ($currentCode === 204) {
            $response->setHeadOnly(true);
            $response->json($result, 204);
        } else {
            $response->json($result);
        }
    }
}
