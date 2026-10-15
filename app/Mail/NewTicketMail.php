<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Ticket $ticket) {}

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
