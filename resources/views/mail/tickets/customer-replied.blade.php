<x-mail::message>
# {{ $ticket->customer->name }} replied

**Ticket #{{ $ticket->id }}:** {{ $ticket->subject }}

**Status:** {{ $ticket->status->label() }}

{{ \Illuminate\Support\Str::limit($reply->body, 400) }}

Please continue the conversation in the helpdesk.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>