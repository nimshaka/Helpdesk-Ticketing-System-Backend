<?php

namespace App\Handlers\User;

use App\Models\User;

class ActivateUserHandler
{
    public function handle(User $target): User
    {
        $target->update(['is_active' => true]);

        return $target->load('roles');
    }
}