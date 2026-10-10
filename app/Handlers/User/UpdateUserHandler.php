<?php

namespace App\Handlers\User;

use App\Models\User;

class UpdateUserHandler
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->update($data);

        return $user->load('roles');
    }
}