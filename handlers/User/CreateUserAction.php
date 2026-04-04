<?php

declare(strict_types=1);

namespace Handlers\User;

use Dtos\User\CreateUserCommand;
use Domain\User\UserCreator;
use Quill\HttpResponse;

/**
 * Action-Domain-Responder (ADR): 
 * Action component connecting the HTTP layer with the Domain logic.
 */
class CreateUserAction
{
    public function __invoke(CreateUserCommand $command): HttpResponse
    {
        // Ideally UserCreator is injected via a DI Container.
        // For demonstration, we instantiate directly.
        $creator = new UserCreator();
        
        $user = $creator->handle(
            email: $command->email,
            name: $command->name,
            password: $command->password
        );

        // Responder: returning standard HttpResponse
        return new HttpResponse([
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]
        ], 201);
    }
}
