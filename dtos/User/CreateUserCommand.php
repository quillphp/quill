<?php

declare(strict_types=1);

namespace Dtos\User;

use Quill\Validation\DTO;
use Quill\Attributes\Required;
use Quill\Attributes\Email;
use Quill\Attributes\MinLength;

class CreateUserCommand extends DTO
{
    public function __construct(
        #[Required, Email]
        public readonly string $email,
        #[Required, MinLength(2)]
        public readonly string $name,
        #[Required, MinLength(8)]
        public readonly string $password,
    ) {}
}
