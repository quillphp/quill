<?php

declare(strict_types=1);

namespace Domain\User;

/**
 * Application Service for creating a User.
 * Following Hexagonal Architecture, this service orchestrates Domain Entities.
 */
class UserCreator
{
    public function handle(string $email, string $name, string $password): User
    {
        // 1. Create purely domain-driven User entity.
        $user = User::create($email, $name, $password);
        
        // 2. Here you would normally use a Repository to save the user.
        // e.g. $this->userRepository->save($user);

        return $user;
    }
}
