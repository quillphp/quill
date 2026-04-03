<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Max
{
    public function __construct(private float|int $max) {}

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!is_numeric($value) || $value > $this->max) {
            return "The field '$field' must be at most {$this->max}.";
        }
        return null;
    }
}
