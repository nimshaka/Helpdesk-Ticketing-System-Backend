<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTicketHandler
{
    use QueuesNotifications;

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

        $this->queueMail($this->notifications()->forTicketCreated($ticket));

        return $ticket;
    }
}
