<?php

namespace Quill\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Regex
{
    public function __construct(
        public string $pattern,
        public ?string $message = null,
    ) {}

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        if (!preg_match($this->pattern, (string)$value)) {
            return $this->message ?? "The field '$field' must match the required pattern.";
        }
        return null;
    }
}
