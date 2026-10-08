<?php

namespace Database\Seeders;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role(UserRole::Admin->value)->firstOrFail();
        $agents = User::role(UserRole::Agent->value)->orderBy('id')->get();
        $customers = User::role(UserRole::Customer->value)->orderBy('id')->get();

        foreach (range(0, 29) as $index) {
            $this->createTicket($index, $admin, $agents, $customers);
        }
    }

    private function assignAgent(Ticket $ticket, int $index, Collection $agents, User $admin, Carbon $at): void
    {
        $agent = $agents[$index % $agents->count()];

        if ($index % 4 === 0) {
            $previous = $agents[($index + 1) % $agents->count()];
            $this->attachAgent($ticket, $previous, $previous, $at, $at->copy()->addHour());
            $this->attachAgent($ticket, $agent, $admin, $at->copy()->addHour(), null);

            return;
        }

        $this->attachAgent($ticket, $agent, $agent, $at, null);
    }

    private function attachAgent(Ticket $ticket, User $agent, User $assignedBy, Carbon $from, ?Carbon $until): void
    {
        $ticket->agents()->attach($agent->id, [
            'assigned_by' => $assignedBy->id,
            'assigned_at' => $from,
            'unassigned_at' => $until,
        ]);
    }

    private function createTicket(int $index, User $admin, Collection $agents, Collection $customers): void
    {
        $customer = $customers[$index % $customers->count()];
        $agent = $agents[$index % $agents->count()];
        $status = $this->statusFor($index);
        $path = $this->pathFor($status);

        $end = $index % 3 === 0
            ? now()->subHours(rand(1, 12))
            : now()->subHours(rand(26, 120));
        $time = $end->copy()->subHours((count($path) + 1) * 4);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status->value,
            'created_at' => $time,
            'updated_at' => $end,
            'last_activity_at' => $end,
        ]);

        $this->recordHistory($ticket, null, TicketStatus::Open, $customer, $time);

        $previous = TicketStatus::Open;

        foreach ($path as $step) {
            $time = $time->copy()->addHours(4);

            if ($previous === TicketStatus::Open) {
                $this->assignAgent($ticket, $index, $agents, $admin, $time);
            }

            $author = $previous === TicketStatus::WaitingForCustomer ? $customer : $agent;

            $this->recordHistory($ticket, $previous, $step, $author, $time);
            $this->recordReply($ticket, $author, $time);

            $previous = $step;
        }
    }

    private function pathFor(TicketStatus $status): array
    {
        return match ($status) {
            TicketStatus::Open => [],
            TicketStatus::InProgress => [TicketStatus::InProgress],
            TicketStatus::WaitingForCustomer => [
                TicketStatus::InProgress,
                TicketStatus::WaitingForCustomer,
            ],
            TicketStatus::Resolved => [
                TicketStatus::InProgress,
                TicketStatus::WaitingForCustomer,
                TicketStatus::InProgress,
                TicketStatus::Resolved,
            ],
        };
    }

    private function recordHistory(Ticket $ticket, ?TicketStatus $from, TicketStatus $to, User $changedBy, Carbon $at): void
    {
        TicketStatusHistory::forceCreate([
            'ticket_id' => $ticket->id,
            'changed_by' => $changedBy->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function recordReply(Ticket $ticket, User $author, Carbon $at): void
    {
        TicketReply::forceCreate([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => fake()->paragraph(),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function statusFor(int $index): TicketStatus
    {
        return match (true) {
            $index < 10 => TicketStatus::Open,
            $index < 18 => TicketStatus::InProgress,
            $index < 23 => TicketStatus::WaitingForCustomer,
            default => TicketStatus::Resolved,
        };
    }
}
