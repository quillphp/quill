<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Numeric
{
    public function validate(string $name, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!is_numeric($value)) {
            return "The field '$name' must be a valid number.";
        }
        return null;
    }
}
