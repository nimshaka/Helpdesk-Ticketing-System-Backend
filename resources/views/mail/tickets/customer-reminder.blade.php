<x-mail::message>
# We are still waiting for your reply

Hello {{ $ticket->customer->name }},

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Last update:** {{ $ticket->last_activity_at->diffForHumans() }}

Our support team asked for more information, and we cannot continue until you answer. Please reply in the helpdesk.

<x-mail::button :url="config('helpdesk.frontend_url')">
Open the helpdesk
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>