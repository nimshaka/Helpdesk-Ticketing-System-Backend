<?php

namespace App\Handlers\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeactivateUserHandler
{
    public function handle(User $target, User $actor): User
    {
        if ($target->is($actor)) {
            throw new AccessDeniedHttpException('You cannot deactivate your own account.');
        }

        DB::transaction(function () use ($target): void {
            $target->update(['is_active' => false]);
            $target->tokens()->delete();
        });

        return $target->load('roles');
    }
}
