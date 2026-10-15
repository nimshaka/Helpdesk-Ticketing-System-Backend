<x-mail::message>
# We received your ticket

Hello {{ $ticket->customer->name }},

Thank you for contacting us. Your request is in our support queue, and an agent will look at it soon. You can follow its progress in the helpdesk.

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Priority:** {{ ucfirst($ticket->priority->value) }}

**Status:** {{ $ticket->status->label() }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>