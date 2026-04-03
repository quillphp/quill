<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class MinLength
{
    public function __construct(private int $min) {}

    public function validate(string $field, mixed $value): ?string
    {
        if (empty($value)) return null;

        if (mb_strlen((string)$value) < $this->min) {
            return "The field '$field' must be at least {$this->min} characters.";
        }
        return null;
    }
}
