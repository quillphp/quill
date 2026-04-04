<?php

declare(strict_types=1);

namespace Handlers\User;

use Quill\HttpResponse;

class UpdateUserAction
{
    public function __invoke(int $id): HttpResponse
    {
        return new HttpResponse(['status' => 'updated', 'id' => $id]);
    }
}
