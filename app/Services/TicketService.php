<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TicketService
{
    public function assignTicketService(User $admin, Ticket $ticket, int $agentId): Ticket
    {
        $agent = User::query()->findOrFail($agentId);

        if (! $agent->hasRole(\App\Enums\UserRole::Agent) || ! $agent->is_active) {
            throw ValidationException::withMessages([
                'agent_id' => ['The selected user must be an active agent.'],
            ]);
        }

        $assigned = DB::transaction(function () use ($admin, $ticket, $agent): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status === TicketStatus::Resolved) {
                throw new ConflictHttpException('Resolved tickets cannot be assigned.');
            }

            $current = $locked->activeAgent()->first();

            if ($current !== null && $current->is($agent)) {
                throw new ConflictHttpException('The ticket is already assigned to this agent.');
            }

            $now = now();

            DB::table('ticket_user')
                ->where('ticket_id', $locked->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            $locked->agents()->attach($agent->id, [
                'assigned_by' => $admin->id,
                'assigned_at' => $now,
            ]);

            if ($locked->status === TicketStatus::Open) {
                app(TicketWorkflowService::class)->ticketTransitionService($locked, TicketStatus::InProgress, $admin, $now);
            } else {
                $locked->update(['last_activity_at' => $now]);
            }

            return $this->withTicketDetails($locked);
        });

        return $assigned;
    }

    public function claimTicketService(User $agent, Ticket $ticket): Ticket
    {
        $claimed = DB::transaction(function () use ($agent, $ticket): Ticket {
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

            app(TicketWorkflowService::class)->ticketTransitionService($locked, TicketStatus::InProgress, $agent, $now);

            return $this->withTicketDetails($locked);
        });

        return $claimed;
    }

    /**
     * @param  array{subject: string, description: string, priority: string}  $data
     */
    public function createTicketService(User $customer, array $data): Ticket
    {
        return DB::transaction(function () use ($customer, $data): Ticket {
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
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listTicketsService(User $user, array $filters): LengthAwarePaginator
    {
        return Ticket::query()
            ->visibleTo($user)
            ->with(['activeAgent', 'customer'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->search($term))
            ->when(
                ($filters['assignment'] ?? null) === 'unassigned',
                fn (Builder $query) => $query->unassigned(),
            )
            ->when(
                ($filters['assignment'] ?? null) === 'mine',
                fn (Builder $query) => $query->heldBy($user),
            )
            ->latest('last_activity_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function showTicketService(User $user, Ticket $ticket): Ticket
    {
        $relations = [
            'customer',
            'activeAgent',
            'replies' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'replies.user.roles',
            'statusHistories' => fn ($query) => $query->orderBy('id'),
            'statusHistories.changedBy',
        ];

        if ($user->hasRole(\App\Enums\UserRole::Admin)) {
            $relations['agents'] = fn ($query) => $query->orderByPivot('assigned_at');
        }

        return $ticket->load($relations);
    }

    public function updateTicketStatusService(User $actor, Ticket $ticket, TicketStatus $to): Ticket
    {
        $updated = DB::transaction(function () use ($actor, $ticket, $to): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! $this->stillAllowed($actor, $locked)) {
                throw new AccessDeniedHttpException('You are no longer assigned to this ticket.');
            }

            app(TicketWorkflowService::class)->ticketTransitionService($locked, $to, $actor);

            return $this->withTicketDetails($locked);
        });

        return $updated;
    }

    private function stillAllowed(User $actor, Ticket $ticket): bool
    {
        return $actor->hasRole(\App\Enums\UserRole::Admin)
            || $ticket->activeAgent()->where('users.id', $actor->id)->exists();
    }

    private function withTicketDetails(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'customer',
            'activeAgent',
            'statusHistories' => fn ($query) => $query->orderBy('id'),
            'statusHistories.changedBy',
        ]);
    }
}
