<?php

namespace App\Handlers\Auth;

use App\Models\User;
use App\Services\AuthService;

class LogoutHandler
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function logoutUserHandler(User $user): void
    {
        $this->authService->userLogoutService($user);
    }
}
