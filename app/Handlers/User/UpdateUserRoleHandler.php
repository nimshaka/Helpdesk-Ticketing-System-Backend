<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;

class UpdateUserRoleHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function updateUserRoleHandler(User $target, User $actor, string $role): User
    {
        return $this->userService->updateUserRoleService($target, $actor, $role);
    }
}
