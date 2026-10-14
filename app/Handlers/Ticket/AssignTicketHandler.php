<?php

namespace App\Handlers\Ticket;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AssignTicketHandler
{
    public function handle(User $admin, Ticket $ticket, int $agentId): Ticket
    {
        $agent = User::query()->findOrFail($agentId);

        if (! $agent->hasRole(UserRole::Agent) || ! $agent->is_active) {
            throw ValidationException::withMessages([
                'agent_id' => ['The selected user must be an active agent.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $ticket, $agent): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status === TicketStatus::Resolved) {
                throw new ConflictHttpException('Resolved tickets cannot be assigned.');
            }

            $current = $locked->activeAgent()->first();

            if ($current !== null && $current->is($agent)) {
                throw new ConflictHttpException('The ticket is already assigned to this agent.');
            }

            $now = now();
            $wasOpen = $locked->status === TicketStatus::Open;

            DB::table('ticket_user')
                ->where('ticket_id', $locked->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            $locked->agents()->attach($agent->id, [
                'assigned_by' => $admin->id,
                'assigned_at' => $now,
            ]);

            if ($wasOpen) {
                $locked->statusHistories()->create([
                    'changed_by' => $admin->id,
                    'from_status' => TicketStatus::Open,
                    'to_status' => TicketStatus::InProgress,
                ]);
                $locked->status = TicketStatus::InProgress;
            }

            $locked->last_activity_at = $now;
            $locked->save();

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
}
