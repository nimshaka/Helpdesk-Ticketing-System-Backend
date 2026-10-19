<?php

namespace App\Handlers\Ticket;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;

class CreateTicketHandler
{
    use QueuesNotifications;

    public function __construct(private readonly TicketService $ticketService)
    {
    }

    /**
     * @param  array{subject: string, description: string, priority: string}  $data
     */
    public function createTicketHandler(User $customer, array $data): Ticket
    {
        $ticket = $this->ticketService->createTicketService($customer, $data);
        $this->queueMailNotifications($this->getTicketNotificationService()->ticketCreatedNotificationService($ticket));

        return $ticket;
    }
}
