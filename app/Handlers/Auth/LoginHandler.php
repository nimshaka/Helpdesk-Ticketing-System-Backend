<?php

namespace App\Handlers\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LoginHandler
{
    /**
     * @return array{token: string, user: User}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid email or password.');
        }

        if (! $user->is_active) {
            throw new AccessDeniedHttpException('Your account has been deactivated.');
        }

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('roles'),
        ];
    }
}