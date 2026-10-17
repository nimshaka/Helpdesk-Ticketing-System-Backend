<?php

namespace App\Console\Commands;

use App\Handlers\Reminder\SendTicketRemindersHandler;
use Illuminate\Console\Command;

class SendTicketReminders extends Command
{
    protected $signature = 'tickets:send-reminders';

    protected $description = 'Queue a reminder for every ticket with no update for the configured number of hours';

    public function handle(SendTicketRemindersHandler $handler): int
    {
        $reminded = $handler->handle();

        $this->info("Queued {$reminded} ticket reminder(s).");

        return self::SUCCESS;
    }
}
