<?php

declare(strict_types=1);

namespace Quill\Validation;

/**
 * Base DTO class for Quill.
 * All user-defined DTOs should extend this.
 */
abstract class DTO
{
    /**
     * Convert DTO to a plain array.
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
