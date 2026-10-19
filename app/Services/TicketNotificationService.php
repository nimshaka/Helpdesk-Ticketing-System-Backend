<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Mail\AgentReminderMail;
use App\Mail\CustomerReminderMail;
use App\Mail\CustomerRepliedMail;
use App\Mail\NewTicketMail;
use App\Mail\StaffRepliedMail;
use App\Mail\TicketAssignedMail;
use App\Mail\TicketReceivedMail;
use App\Mail\TicketResolvedMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\PendingMail;

class TicketNotificationService
{
    /**
     * @return list<PendingMail>
     */
    public function ticketAssignedNotificationService(Ticket $ticket, User $agent, User $actor): array
    {
        $ticket->loadMissing('customer');

        return $this->withoutTicketNotificationActorService(
            [new PendingMail($agent, new TicketAssignedMail($ticket, $actor))],
            $actor,
        );
    }

    /**
     * @return list<PendingMail>
     */
    public function ticketCreatedNotificationService(Ticket $ticket): array
    {
        $ticket->loadMissing('customer');

        return [
            new PendingMail($ticket->customer, new TicketReceivedMail($ticket)),
            new PendingMail($this->ticketSupportAddressService(), new NewTicketMail($ticket)),
        ];
    }

    /**
     * @return list<PendingMail>
     */
    public function ticketReminderNotificationService(Ticket $ticket): array
    {
        $ticket->loadMissing(['customer', 'activeAgent']);

        $pending = match ($ticket->status) {
            TicketStatus::InProgress => $ticket->activeAgent
                ->map(fn (User $agent): PendingMail => new PendingMail($agent, new AgentReminderMail($ticket)))
                ->all(),
            TicketStatus::WaitingForCustomer => [
                new PendingMail($ticket->customer, new CustomerReminderMail($ticket)),
            ],
            default => [],
        };

        return $this->onlyActiveTicketRecipientsService($pending);
    }

    /**
     * @return list<PendingMail>
     */
    public function ticketReplyNotificationService(Ticket $ticket, TicketReply $reply): array
    {
        $ticket->loadMissing(['customer', 'activeAgent']);
        $reply->loadMissing('user');

        if ((int) $reply->user_id === (int) $ticket->customer_id) {
            $pending = $ticket->activeAgent
                ->map(fn (User $agent): PendingMail => new PendingMail($agent, new CustomerRepliedMail($ticket, $reply)))
                ->all();
        } else {
            $pending = [new PendingMail($ticket->customer, new StaffRepliedMail($ticket, $reply))];
        }

        return $this->withoutTicketNotificationActorService($pending, $reply->user);
    }

    /**
     * @return list<PendingMail>
     */
    public function ticketResolvedNotificationService(Ticket $ticket, User $actor): array
    {
        $ticket->loadMissing('customer');

        return $this->withoutTicketNotificationActorService(
            [new PendingMail($ticket->customer, new TicketResolvedMail($ticket))],
            $actor,
        );
    }

    public function ticketSupportAddressService(): string
    {
        return (string) config('helpdesk.support_email');
    }

    /**
     * @param  list<PendingMail>  $pending
     * @return list<PendingMail>
     */
    private function onlyActiveTicketRecipientsService(array $pending): array
    {
        return array_values(array_filter(
            $pending,
            fn (PendingMail $item): bool => ! ($item->recipient instanceof User) || $item->recipient->is_active,
        ));
    }

    /**
     * @param  list<PendingMail>  $pending
     * @return list<PendingMail>
     */
    private function withoutTicketNotificationActorService(array $pending, User $actor): array
    {
        return array_values(array_filter(
            $pending,
            fn (PendingMail $item): bool => ! ($item->recipient instanceof User && $item->recipient->is($actor)),
        ));
    }
}
