<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflowService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClaimTicketHandler
{
    public function handle(User $agent, Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($agent, $ticket): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->activeAgent()->exists()) {
                throw new ConflictHttpException('This ticket has already been assigned.');
            }

            if ($locked->status !== TicketStatus::Open) {
                throw new ConflictHttpException('Only open tickets can be claimed.');
            }

            $now = now();

            $locked->agents()->attach($agent->id, [
                'assigned_by' => $agent->id,
                'assigned_at' => $now,
            ]);

            $this->workflow()->transition($locked, TicketStatus::InProgress, $agent, $now);

            return $this->withDetails($locked);
        });
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
