<?php

declare(strict_types=1);

namespace Handlers\User;

use Quill\HttpResponse;

class DeleteUserAction
{
    public function __invoke(int $id): HttpResponse
    {
        return new HttpResponse(['status' => 'deleted', 'id' => $id]);
    }
}
