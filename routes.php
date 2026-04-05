<?php

declare(strict_types=1);

use Handlers\User\ListUsersAction;
use Handlers\User\GetUserAction;
use Handlers\User\CreateUserAction;
use Handlers\User\UpdateUserAction;
use Handlers\User\DeleteUserAction;

/** @var \Quill\App $app */

$app->get('/hello', fn() => ['message' => 'Quill is ready']);

$app->group('/api', function ($app) {
    $app->group('/v1', function ($app) {
        $app->get('/users', [ListUsersAction::class, '__invoke']);
        $app->get('/users/{id}', [GetUserAction::class, '__invoke']);
        $app->post('/users', [CreateUserAction::class, '__invoke']);
        $app->put('/users/{id}', [UpdateUserAction::class, '__invoke']);
        $app->delete('/users/{id}', [DeleteUserAction::class, '__invoke']);
    });
});
