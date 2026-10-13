<?php

namespace App\Handlers\Ticket;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class ShowTicketHandler
{
    public function handle(User $user, Ticket $ticket): Ticket
    {
        $relations = [
            'customer',
            'activeAgent',
            'replies' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'replies.user.roles',
            'statusHistories' => fn ($query) => $query->orderBy('id'),
            'statusHistories.changedBy',
        ];

        if ($user->hasRole(UserRole::Admin)) {
            $relations['agents'] = fn ($query) => $query->orderByPivot('assigned_at');
        }

        return $ticket->load($relations);
    }
}
