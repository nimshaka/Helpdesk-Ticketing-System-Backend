<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
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
