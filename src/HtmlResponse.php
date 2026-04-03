<?php

declare(strict_types=1);

namespace Quill;

/**
 * HTML response value object for handlers.
 */
class HtmlResponse
{
    public function __construct(
        public readonly string $html,
        public readonly int $status = 200,
        /** @var array<string, string> */
        public readonly array $headers = [],
    ) {}
}
