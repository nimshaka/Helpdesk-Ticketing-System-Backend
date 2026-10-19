<?php

namespace App\Handlers\Ticket;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

class ClaimTicketHandler
{
    use QueuesNotifications;

    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function claimTicketHandler(User $agent, Ticket $ticket): Ticket
    {
        $claimed = $this->ticketService->claimTicketService($agent, $ticket);
        $this->queueMailNotifications($this->getTicketNotificationService()->ticketAssignedNotificationService($claimed, $agent, $agent));

        return $claimed;
    }
}
