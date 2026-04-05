<?php

declare(strict_types=1);

namespace Quill\Routing;

/**
 * Named constants for Router dispatch status codes.
 * Replaces magic integers (1, 2, 3) used in internal routing logic.
 */
final class RouterStatus
{
    public const FOUND              = 1;
    public const NOT_FOUND          = 2;
    public const METHOD_NOT_ALLOWED = 3;

    private function __construct() {}
}
