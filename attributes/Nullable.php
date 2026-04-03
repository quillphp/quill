<?php

namespace Quill\Attributes;

use Attribute;

/**
 * Marks a property as nullable (for OpenAPI documentation and Validator logic).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Nullable
{
}
