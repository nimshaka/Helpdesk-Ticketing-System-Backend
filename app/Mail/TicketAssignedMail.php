<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketAssignedMail extends TicketMailable
{
    public function __construct(Ticket $ticket, public User $assignedBy)
    {
        parent::__construct($ticket);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.assigned');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Ticket #{$this->ticket->id} was assigned to you");
    }
}