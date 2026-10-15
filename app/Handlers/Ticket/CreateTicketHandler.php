<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Mail\NewTicketMail;
use App\Mail\TicketReceivedMail;
use App\Models\Ticket;
use App\Models\User;
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

        $this->queueEmails($ticket);

        return $ticket;
    }

    private function queueEmails(Ticket $ticket): void
    {
        Mail::to($ticket->customer)->queue(new TicketReceivedMail($ticket));
        Mail::to(config('helpdesk.support_email'))->queue(new NewTicketMail($ticket));
    }
}
