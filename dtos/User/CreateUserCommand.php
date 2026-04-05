<?php

declare(strict_types=1);

namespace Dtos\User;

use Quill\Validation\DTO;

class CreateUserCommand extends DTO
{
    public string $email;
    public string $name;
    public string $password;
}
