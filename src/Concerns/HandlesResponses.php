<?php

declare(strict_types=1);

namespace Quill\Concerns;

use Quill\Http\Response;
use Quill\Http\HtmlResponse;
use Quill\Http\HttpResponse;

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

        if (!empty($result)) {
            $status = 200;
            if (is_array($result) && isset($result['status']) && is_scalar($result['status'])) {
                $status = (int)$result['status'];
            }
            $response->json($result, $status);
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
