<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:send-reminders')
    ->dailyAt(config('helpdesk.reminder_time'))
    ->timezone(config('helpdesk.reminder_timezone'))
    ->withoutOverlapping();
