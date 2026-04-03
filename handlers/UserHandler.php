<?php

namespace Handlers;

use Quill\Request;

class UserHandler
{
    public function index(Request $request): array
    {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
    }

    public function show(Request $request): array
    {
        $id = $request->param('id');
        return ['id' => (int)$id, 'name' => 'User ' . $id];
    }

    public function store(\Dtos\CreateUserDTO $dto): array
    {
        return [
            'id' => 99,
            ...$dto->toArray(),
        ];
    }

    public function update(Request $request): array
    {
        return ['status' => 'updated', 'id' => $request->param('id')];
    }

    public function destroy(Request $request): array
    {
        return ['status' => 'deleted', 'id' => $request->param('id')];
    }
}
