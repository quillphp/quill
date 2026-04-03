<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Boolean
{
    public function validate(string $name, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false', true, false], true)) {
            return "The field '$name' must be a valid boolean.";
        }
        return null;
    }
}
