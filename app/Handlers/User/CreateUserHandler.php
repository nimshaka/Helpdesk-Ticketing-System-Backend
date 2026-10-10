<?php

namespace App\Handlers\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUserHandler
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles([$data['role']]);

            return $user->load('roles');
        });
    }
}