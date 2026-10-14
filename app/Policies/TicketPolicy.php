<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function claim(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(UserRole::Agent) && $user->is_active;
    }

    public function view(User $user, Ticket $ticket): Response
    {
        $allowed = Ticket::query()
            ->visibleTo($user)
            ->whereKey($ticket->getKey())
            ->exists();

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }
}
