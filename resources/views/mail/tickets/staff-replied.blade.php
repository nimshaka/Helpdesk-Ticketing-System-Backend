<x-mail::message>
# There is a new reply on your ticket

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**{{ $reply->user->name }} wrote:**

{{ \Illuminate\Support\Str::limit($reply->body, 400) }}

@if ($ticket->status === \App\Enums\TicketStatus::WaitingForCustomer)
We need more information from you. Please reply in the helpdesk.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>