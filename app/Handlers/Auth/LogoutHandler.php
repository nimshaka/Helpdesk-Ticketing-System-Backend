<?php

namespace App\Handlers\Auth;

use App\Models\User;

class LogoutHandler
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}