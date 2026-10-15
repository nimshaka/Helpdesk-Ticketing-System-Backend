<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerRepliedMail extends TicketMailable
{
    public function __construct(Ticket $ticket, public TicketReply $reply)
    {
        parent::__construct($ticket);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.customer-replied');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->ticket->customer->name} replied on ticket #{$this->ticket->id}",
        );
    }
}
