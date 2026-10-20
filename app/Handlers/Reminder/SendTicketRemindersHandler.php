<?php

namespace App\Handlers\Reminder;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Services\ReminderService;

class SendTicketRemindersHandler
{
    use QueuesNotifications;

    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    /**
     * Queue a reminder for every stale ticket that was not reminded today.
     *
     * @return int how many tickets were reminded
     */
    public function sendTicketRemindersCommandHandler(): int
    {
        return $this->reminderService->sendTicketRemindersService(function (Ticket $ticket): bool {
            $pending = $this->getTicketNotificationService()->ticketReminderNotificationService($ticket);

            if ($pending === []) {
                return false;
            }

            $this->queueMailNotifications($pending);
            $ticket->forceFill(['last_reminded_at' => now()])->saveQuietly();

            return true;
        });
    }
}
