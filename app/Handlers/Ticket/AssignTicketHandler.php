<?php

namespace App\Handlers\Ticket;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

class AssignTicketHandler
{
    use QueuesNotifications;

    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function assignTicketHandler(User $admin, Ticket $ticket, int $agentId): Ticket
    {
        $assigned = $this->ticketService->assignTicketService($admin, $ticket, $agentId);
        $agent = User::query()->findOrFail($agentId);
        $this->queueMailNotifications($this->getTicketNotificationService()->ticketAssignedNotificationService($assigned, $agent, $admin));

        return $assigned;
    }
}
