<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketResolvedMail extends TicketMailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.resolved');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your ticket #{$this->ticket->id} was resolved");
    }
}
