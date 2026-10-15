<?php

namespace App\Services;

use App\Mail\NewTicketMail;
use App\Mail\TicketReceivedMail;
use App\Models\Ticket;
use App\Support\PendingMail;

class TicketNotificationService
{
    /**
     * Who must be told when a customer creates a ticket.
     *
     * @return list<PendingMail>
     */
    public function forTicketCreated(Ticket $ticket): array
    {
        $ticket->loadMissing('customer');

        return [
            new PendingMail($ticket->customer, new TicketReceivedMail($ticket)),
            new PendingMail($this->supportAddress(), new NewTicketMail($ticket)),
        ];
    }

    public function supportAddress(): string
    {
        return (string) config('helpdesk.support_email');
    }
}
