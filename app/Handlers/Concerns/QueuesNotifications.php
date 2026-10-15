<?php

namespace App\Handlers\Concerns;

use App\Services\TicketNotificationService;
use App\Support\PendingMail;
use Illuminate\Support\Facades\Mail;

trait QueuesNotifications
{
    protected function notifications(): TicketNotificationService
    {
        return app(TicketNotificationService::class);
    }

    /**
     * @param  list<PendingMail>  $pending
     */
    protected function queueMail(array $pending): void
    {
        foreach ($pending as $item) {
            Mail::to($item->recipient)->queue($item->mailable);
        }
    }
}
