<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

class UpdateTicketStatusHandler
{
    use QueuesNotifications;

    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function updateTicketStatusHandler(User $actor, Ticket $ticket, TicketStatus $to): Ticket
    {
        $updated = $this->ticketService->updateTicketStatusService($actor, $ticket, $to);

        if ($to === TicketStatus::Resolved) {
            $this->queueMailNotifications($this->getTicketNotificationService()->ticketResolvedNotificationService($updated, $actor));
        }

        return $updated;
    }
}
