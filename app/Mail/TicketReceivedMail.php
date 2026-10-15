<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Ticket $ticket)
    {
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets.received');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We received your ticket #{$this->ticket->id}");
    }
}