<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;

class CreateUserHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUserHandler(array $data): User
    {
        return $this->userService->createUserService($data);
    }
}
