<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MaxLength
{
    public function __construct(private int $max) {}

    public function validate(string $field, mixed $value): ?string
    {
        if (empty($value)) return null;

        if (mb_strlen((string)$value) > $this->max) {
            return "The field '$field' must be at most {$this->max} characters.";
        }
        return null;
    }
}
