<?php

namespace App\Handlers\User;

use App\Models\User;
use Illuminate\Support\Arr;

class UpdateUserHandler
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->update(Arr::only($data, ['name', 'email']));

        return $user->load('roles');
    }
}
