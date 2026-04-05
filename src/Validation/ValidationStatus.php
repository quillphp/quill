<?php

declare(strict_types=1);

namespace Quill\Validation;

/**
 * Named constants for Validator result codes from the native FFI core.
 */
final class ValidationStatus
{
    public const SUCCESS          = 0;
    public const VALIDATION_ERROR = 1;
    public const SYSTEM_ERROR     = 2;

    private function __construct() {}
}
