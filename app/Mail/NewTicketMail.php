<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewTicketMail extends TicketMailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.created');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New ticket #{$this->ticket->id} from {$this->ticket->customer->name}",
        );
    }
}
