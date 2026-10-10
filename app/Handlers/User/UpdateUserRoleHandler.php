<?php

namespace App\Handlers\User;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UpdateUserRoleHandler
{
    public function handle(User $target, User $actor, string $role): User
    {
        if ($target->is($actor)) {
            throw new AccessDeniedHttpException('You cannot change your own role.');
        }

        $target->syncRoles([$role]);

        return $target->load('roles');
    }
}