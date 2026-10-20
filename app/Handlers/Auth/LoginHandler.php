<?php

namespace App\Handlers\Auth;

use App\Models\User;
use App\Services\AuthService;

class LoginHandler
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * @return array{token: string, user: User}
     */
    public function loginUserHandler(string $email, string $password): array
    {
        return $this->authService->userLoginService($email, $password);
    }
}
