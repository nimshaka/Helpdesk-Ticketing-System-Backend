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
     * Who must be reminded about a stale ticket: the agent when the agent owes
     * the next action, the customer when the customer does.
     *
     * @return list<PendingMail>
     */
    public function forReminder(Ticket $ticket): array
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

        return $this->onlyActive($pending);
    }

    /**
     * Who must be told about a new reply: the agent when the customer wrote it,
     * the customer when staff wrote it.
     *
     * @return list<PendingMail>
     */
    public function forReply(Ticket $ticket, TicketReply $reply): array
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

        return $this->withoutActor($pending, $reply->user);
    }

    /**
     * Who must be told when a ticket gets a new agent.
     *
     * @return list<PendingMail>
     */
    public function forTicketAssigned(Ticket $ticket, User $agent, User $actor): array
    {
        $ticket->loadMissing('customer');

        return $this->withoutActor(
            [new PendingMail($agent, new TicketAssignedMail($ticket, $actor))],
            $actor,
        );
    }

    /**
     * Who must be told when a customer creates a ticket.
     *
     * The acknowledgement goes to the customer about their own action on purpose,
     * so this method does not filter out the actor.
     *
     * @return list<PendingMail>
     */
    public function forTicketCreated(Ticket $ticket): array
    {
        $ticket->loadMissing('customer');

        return [
            new PendingMail($ticket->customer, new TicketReceivedMail($ticket)),
            new PendingMail($this->supportAddress(), new NewTicketMail($ticket)),
        ];
    }

    /**
     * Who must be told when a ticket is resolved: the customer.
     *
     * @return list<PendingMail>
     */
    public function forTicketResolved(Ticket $ticket, User $actor): array
    {
        $ticket->loadMissing('customer');

        return $this->withoutActor(
            [new PendingMail($ticket->customer, new TicketResolvedMail($ticket))],
            $actor,
        );
    }

    public function supportAddress(): string
    {
        return (string) config('helpdesk.support_email');
    }

    /**
     * Never email a deactivated account.
     *
     * @param  list<PendingMail>  $pending
     * @return list<PendingMail>
     */
    private function onlyActive(array $pending): array
    {
        return array_values(array_filter(
            $pending,
            fn (PendingMail $item): bool => ! ($item->recipient instanceof User) || $item->recipient->is_active,
        ));
    }

    /**
     * The rule "never email a person about their own action", written once.
     *
     * @param  list<PendingMail>  $pending
     * @return list<PendingMail>
     */
    private function withoutActor(array $pending, User $actor): array
    {
        return array_values(array_filter(
            $pending,
            fn (PendingMail $item): bool => ! ($item->recipient instanceof User && $item->recipient->is($actor)),
        ));
    }
}
