<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;

class DeactivateUserHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function deactivateUserHandler(User $target, User $actor): User
    {
        return $this->userService->deactivateUserService($target, $actor);
    }
}
