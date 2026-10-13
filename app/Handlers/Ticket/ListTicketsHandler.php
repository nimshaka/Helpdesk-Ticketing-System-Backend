<?php

namespace App\Handlers\Ticket;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTicketsHandler
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $user, array $filters): LengthAwarePaginator
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
}
