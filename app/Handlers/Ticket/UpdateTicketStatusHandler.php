<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflowService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UpdateTicketStatusHandler
{
    use QueuesNotifications;

    public function handle(User $actor, Ticket $ticket, TicketStatus $to): Ticket
    {
        $updated = DB::transaction(function () use ($actor, $ticket, $to): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! $this->stillAllowed($actor, $locked)) {
                throw new AccessDeniedHttpException('You are no longer assigned to this ticket.');
            }

            $this->workflow()->transition($locked, $to, $actor);

            return $this->withDetails($locked);
        });

        if ($to === TicketStatus::Resolved) {
            $this->queueMail($this->notifications()->forTicketResolved($updated, $actor));
        }

        return $updated;
    }

    private function stillAllowed(User $actor, Ticket $ticket): bool
    {
        return $actor->hasRole(UserRole::Admin)
            || $ticket->activeAgent()->where('users.id', $actor->id)->exists();
    }

    private function withDetails(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'customer',
            'activeAgent',
            'statusHistories' => fn ($query) => $query->orderBy('id'),
            'statusHistories.changedBy',
        ]);
    }

    private function workflow(): TicketWorkflowService
    {
        return app(TicketWorkflowService::class);
    }
}
