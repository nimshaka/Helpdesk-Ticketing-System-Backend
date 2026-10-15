<?php

namespace App\Handlers\Dashboard;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GetDashboardHandler
{
    private const ACTIVITY_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $counts = $this->statusCounts($user);

        $dashboard = [
            'role' => $user->getRoleNames()->first(),
            'total' => array_sum(array_column($counts, 'count')),
            'counts' => $counts,
        ];

        if ($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Agent)) {
            $dashboard['unassigned'] = Ticket::query()->visibleTo($user)->unassigned()->count();
        }

        if ($user->hasRole(UserRole::Agent)) {
            $dashboard['assigned_to_me'] = Ticket::query()->heldBy($user)->count();
        }

        $dashboard['recent_activity'] = $this->recentActivity($user)->all();

        return $dashboard;
    }

    /**
     * @return array<string, mixed>
     */
    private function activityFromHistory(TicketStatusHistory $history): array
    {
        $actor = $history->changedBy->name;

        $summary = $history->from_status === null
            ? "{$actor} created the ticket"
            : "{$actor} changed the status from {$history->from_status->label()} to {$history->to_status->label()}";

        return [
            'type' => 'status_change',
            'summary' => $summary,
            'actor' => ['id' => $history->changedBy->id, 'name' => $actor],
            'ticket' => ['id' => $history->ticket->id, 'subject' => $history->ticket->subject],
            'occurred_at' => $history->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityFromReply(TicketReply $reply): array
    {
        return [
            'type' => 'reply',
            'summary' => "{$reply->user->name} replied",
            'preview' => Str::limit($reply->body, 80),
            'actor' => ['id' => $reply->user->id, 'name' => $reply->user->name],
            'ticket' => ['id' => $reply->ticket->id, 'subject' => $reply->ticket->subject],
            'occurred_at' => $reply->created_at,
        ];
    }

    private function recentActivity(User $user): Collection
    {
        $replies = TicketReply::query()
            ->whereHas('ticket', fn (Builder $ticket) => $ticket->visibleTo($user))
            ->with(['user', 'ticket:id,subject'])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (TicketReply $reply): array => $this->activityFromReply($reply));

        $changes = TicketStatusHistory::query()
            ->whereHas('ticket', fn (Builder $ticket) => $ticket->visibleTo($user))
            ->with(['changedBy', 'ticket:id,subject'])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (TicketStatusHistory $history): array => $this->activityFromHistory($history));

        return $replies->concat($changes)
            ->sortByDesc(fn (array $item): int => $item['occurred_at']->getTimestamp())
            ->take(self::ACTIVITY_LIMIT)
            ->values();
    }

    /**
     * @return list<array{status: string, label: string, count: int}>
     */
    private function statusCounts(User $user): array
    {
        $totals = Ticket::query()
            ->visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(TicketStatus::cases())
            ->map(fn (TicketStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($totals[$status->value] ?? 0),
            ])
            ->all();
    }
}
