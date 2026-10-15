<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffRepliedMail extends TicketMailable
{
    public function __construct(Ticket $ticket, public TicketReply $reply)
    {
        parent::__construct($ticket);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.staff-replied');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New reply on your ticket #{$this->ticket->id}");
    }
}