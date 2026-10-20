<?php

namespace App\Handlers\Ticket;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\TicketReplyService;

class CreateTicketReplyHandler
{
    use QueuesNotifications;

    public function __construct(private readonly TicketReplyService $ticketReplyService)
    {
    }

    public function createTicketReplyHandler(User $author, Ticket $ticket, string $body): TicketReply
    {
        $reply = $this->ticketReplyService->createTicketReplyService($author, $ticket, $body);
        $this->queueMailNotifications($this->getTicketNotificationService()->ticketReplyNotificationService($reply->ticket, $reply));

        return $reply;
    }
}
