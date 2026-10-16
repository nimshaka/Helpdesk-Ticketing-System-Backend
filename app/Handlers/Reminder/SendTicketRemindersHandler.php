<?php

namespace App\Handlers\Reminder;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class SendTicketRemindersHandler
{
    use QueuesNotifications;

    /**
     * Queue a reminder for every stale ticket that was not reminded today.
     *
     * @return int how many tickets were reminded
     */
    public function handle(): int
    {
        $reminded = 0;

        Ticket::query()
            ->needingReminder()
            ->with(['customer', 'activeAgent'])
            ->chunkById(100, function (Collection $tickets) use (&$reminded): void {
                foreach ($tickets as $ticket) {
                    $pending = $this->notifications()->forReminder($ticket);

                    if ($pending === []) {
                        continue;
                    }

                    $this->queueMail($pending);

                    $ticket->forceFill(['last_reminded_at' => now()])->saveQuietly();

                    $reminded++;
                }
            });

        return $reminded;
    }
}
