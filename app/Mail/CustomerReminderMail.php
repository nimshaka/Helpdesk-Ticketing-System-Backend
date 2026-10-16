<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerReminderMail extends TicketMailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.customer-reminder');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We are waiting for your reply on ticket #{$this->ticket->id}");
    }
}
