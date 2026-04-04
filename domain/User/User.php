<?php

declare(strict_types=1);

namespace Domain\User;

/**
 * Domain Entity representing a User.
 * This class is independent of the framework's HTTP layer.
 */
class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
    ) {
    }

    public static function create(string $email, string $name, string $password): self
    {
        // Add business rules inside the Entity
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email provided.");
        }

        return new self(
            id: uniqid('user_', true),
            email: $email,
            name: $name
        );
    }
}
