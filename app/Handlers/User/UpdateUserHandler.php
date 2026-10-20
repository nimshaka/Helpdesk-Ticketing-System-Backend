<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;

class UpdateUserHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUserHandler(User $user, array $data): User
    {
        return $this->userService->updateUserService($user, $data);
    }
}
