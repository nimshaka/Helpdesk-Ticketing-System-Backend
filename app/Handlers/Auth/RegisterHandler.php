<?php

namespace App\Handlers\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterHandler
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, user: User}
     */
    public function handle(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->assignRole(UserRole::Customer);

            return $user;
        });

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('roles'),
        ];
    }
}
