<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Email
{
    public function validate(string $field, mixed $value): ?string
    {
        if (empty($value)) return null;

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The field '$field' must be a valid email address.";
        }
        return null;
    }
}
