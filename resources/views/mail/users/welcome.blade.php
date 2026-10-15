<x-mail::message>
# Welcome, {{ $user->name }}

Your {{ config('app.name') }} account is ready. You are registered as a **{{ $user->getRoleNames()->first() }}**.

You can now report problems, follow their progress and read every reply in one place.

<x-mail::button :url="config('helpdesk.frontend_url')">
Open the helpdesk
</x-mail::button>

If you did not create this account, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>