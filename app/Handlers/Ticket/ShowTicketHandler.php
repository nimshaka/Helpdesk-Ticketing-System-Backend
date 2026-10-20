<?php

namespace App\Handlers\Ticket;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

class ShowTicketHandler
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function showTicketHandler(User $user, Ticket $ticket): Ticket
    {
        return $this->ticketService->showTicketService($user, $ticket);
    }
}
