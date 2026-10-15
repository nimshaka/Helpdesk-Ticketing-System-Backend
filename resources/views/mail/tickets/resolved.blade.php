<x-mail::message>
# Your ticket was resolved

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

Our support team marked this request as resolved. You can still read the complete conversation and history in the helpdesk.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>