<?php

namespace Dtos;

use Quill\DTO;
use Quill\Attributes\{Required, Email, MinLength};

class CreateUserDTO extends DTO
{
    #[Required]
    #[MinLength(2)]
    public string $name;

    #[Required]
    #[Email]
    public string $email;
}
