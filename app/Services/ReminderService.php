<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class ReminderService
{
    /**
     * Process ticket reminders in chunks and return count.
     *
     * @return int how many tickets were reminded
     */
    public function sendTicketRemindersService(callable $processReminder): int
    {
        $reminded = 0;

        Ticket::query()
            ->needingReminder()
            ->with(['customer', 'activeAgent'])
            ->chunkById(100, function (Collection $tickets) use (&$reminded, $processReminder): void {
                foreach ($tickets as $ticket) {
                    if ($processReminder($ticket)) {
                        $reminded++;
                    }
                }
            });

        return $reminded;
    }
}
