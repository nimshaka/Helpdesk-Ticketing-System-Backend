<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketReceivedMail extends TicketMailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.received');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We received your ticket #{$this->ticket->id}");
    }
}