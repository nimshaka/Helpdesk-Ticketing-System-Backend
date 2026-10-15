<x-mail::message>
# New ticket in the queue

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Customer:** {{ $ticket->customer->name }} ({{ $ticket->customer->email }})

**Priority:** {{ ucfirst($ticket->priority->value) }}

{{ \Illuminate\Support\Str::limit($ticket->description, 300) }}

Please claim it if you can take it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>