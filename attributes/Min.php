<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Min
{
    public function __construct(private float|int $min) {}

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!is_numeric($value) || $value < $this->min) {
            return "The field '$field' must be at least {$this->min}.";
        }
        return null;
    }
}
