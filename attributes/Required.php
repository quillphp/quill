<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Required
{
    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return "The field '$field' is required.";
        }
        return null;
    }
}
