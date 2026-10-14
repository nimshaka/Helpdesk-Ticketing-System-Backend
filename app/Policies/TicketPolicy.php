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

    public function changeStatus(User $user, Ticket $ticket): Response
    {
        return $this->staffAction($user, $ticket, 'Only the assigned agent or an admin can change the status.');
    }

    public function claim(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(UserRole::Agent) && $user->is_active;
    }

    public function reply(User $user, Ticket $ticket): Response
    {
        if ($user->hasRole(UserRole::Customer)) {
            return $this->view($user, $ticket);
        }

        return $this->staffAction($user, $ticket, 'Claim this ticket before replying.');
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

    private function holds(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(UserRole::Agent)
            && $ticket->activeAgent()->where('users.id', $user->id)->exists();
    }

    private function staffAction(User $user, Ticket $ticket, string $message): Response
    {
        $visible = $this->view($user, $ticket);

        if ($visible->denied()) {
            return $visible;
        }

        if ($user->hasRole(UserRole::Admin) || $this->holds($user, $ticket)) {
            return Response::allow();
        }

        return Response::deny($message);
    }
}