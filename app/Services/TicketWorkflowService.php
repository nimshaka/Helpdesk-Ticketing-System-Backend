<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TicketWorkflowService
{
    public function acceptsReplies(TicketStatus $status): bool
    {
        return $status !== TicketStatus::Resolved;
    }

    /**
     * @return list<TicketStatus>
     */
    public function allowedTransitions(TicketStatus $from): array
    {
        return match ($from) {
            TicketStatus::Open => [TicketStatus::InProgress],
            TicketStatus::InProgress => [TicketStatus::WaitingForCustomer, TicketStatus::Resolved],
            TicketStatus::WaitingForCustomer => [TicketStatus::InProgress, TicketStatus::Resolved],
            TicketStatus::Resolved => [],
        };
    }

    public function canTransition(TicketStatus $from, TicketStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions($from), true);
    }

    public function statusAfterCustomerReply(TicketStatus $current): ?TicketStatus
    {
        return $current === TicketStatus::WaitingForCustomer ? TicketStatus::InProgress : null;
    }

    public function transition(Ticket $ticket, TicketStatus $to, User $actor, ?Carbon $at = null): Ticket
    {
        $from = $ticket->status;

        if (! $this->canTransition($from, $to)) {
            throw new ConflictHttpException("A ticket cannot move from {$from->label()} to {$to->label()}.");
        }

        $ticket->statusHistories()->create([
            'changed_by' => $actor->id,
            'from_status' => $from,
            'to_status' => $to,
        ]);

        $ticket->update([
            'status' => $to,
            'last_activity_at' => $at ?? now(),
        ]);

        return $ticket;
    }
}
