<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use App\Support\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateTicketHandler
{
    /**
     * @param  array{subject: string, description: string, priority: string}  $data
     */
    public function handle(User $customer, array $data): Ticket
    {
        $ticket = DB::transaction(function () use ($customer, $data): Ticket {
            $ticket = Ticket::create([
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'status' => TicketStatus::Open,
                'customer_id' => $customer->id,
                'last_activity_at' => now(),
            ]);

            $ticket->statusHistories()->create([
                'changed_by' => $customer->id,
                'from_status' => null,
                'to_status' => TicketStatus::Open,
            ]);

            return $ticket->load('customer');
        });

        $this->queue($this->notifications()->forTicketCreated($ticket));

        return $ticket;
    }

    private function notifications(): TicketNotificationService
    {
        return app(TicketNotificationService::class);
    }

    /**
     * @param  list<PendingMail>  $pending
     */
    private function queue(array $pending): void
    {
        foreach ($pending as $item) {
            Mail::to($item->recipient)->queue($item->mailable);
        }
    }
}
