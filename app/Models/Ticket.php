<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'description',
        'priority',
        'status',
        'customer_id',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
        ];
    }

    public function activeAgent(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_user')
            ->withPivot('assigned_by', 'assigned_at', 'unassigned_at')
            ->wherePivotNull('unassigned_at');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_user')
            ->withPivot('assigned_by', 'assigned_at', 'unassigned_at');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function scopeHeldBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'activeAgent',
            fn (Builder $agent) => $agent->where('users.id', $user->id),
        );
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('subject', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereDoesntHave('activeAgent');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin)) {
            return $query;
        }

        if ($user->hasRole(UserRole::Agent)) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->unassigned()->orWhere(fn (Builder $mine) => $mine->heldBy($user));
            });
        }

        if ($user->hasRole(UserRole::Customer)) {
            return $query->where('customer_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }
}
