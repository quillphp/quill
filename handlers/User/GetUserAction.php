<?php

declare(strict_types=1);

namespace Handlers\User;

use Quill\HttpResponse;

class GetUserAction
{
    public function __invoke(int $id): HttpResponse
    {
        return new HttpResponse(['id' => $id, 'name' => 'User ' . $id]);
    }
}
