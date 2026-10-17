<x-mail::message>
# A ticket has had no update for a while

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Customer:** {{ $ticket->customer->name }}

**Status:** {{ $ticket->status->label() }}

**Last update:** {{ $ticket->last_activity_at->diffForHumans() }}

You are the assigned agent, so the next support action is yours. Please reply, ask for information, or resolve the ticket.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>