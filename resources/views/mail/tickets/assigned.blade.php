<x-mail::message>
# A ticket was assigned to you

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Customer:** {{ $ticket->customer->name }}

**Priority:** {{ ucfirst($ticket->priority->value) }}

**Status:** {{ $ticket->status->label() }}

**Assigned by:** {{ $assignedBy->name }}

{{ \Illuminate\Support\Str::limit($ticket->description, 300) }}

You are now responsible for the next support action on this ticket.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>