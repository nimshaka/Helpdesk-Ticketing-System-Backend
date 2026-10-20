<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;

class ActivateUserHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function activateUserHandler(User $target): User
    {
        return $this->userService->activateUserService($target);
    }
}
