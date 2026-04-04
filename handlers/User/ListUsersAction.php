<?php

declare(strict_types=1);

namespace Handlers\User;

use Quill\HttpResponse;

class ListUsersAction
{
    public function __invoke(): HttpResponse
    {
        return new HttpResponse([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
    }
}
