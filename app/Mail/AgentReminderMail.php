<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AgentReminderMail extends TicketMailable
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.agent-reminder');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reminder: ticket #{$this->ticket->id} needs your attention");
    }
}