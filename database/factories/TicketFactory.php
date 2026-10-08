<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(TicketPriority::cases())->value,
            'status' => TicketStatus::Open->value,
            'customer_id' => User::factory(),
            'last_activity_at' => now(),
        ];
    }

    /**
     * Indicate that nothing happened on the ticket for more than 24 hours.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity_at' => now()->subHours(fake()->numberBetween(25, 72)),
        ]);
    }

    /**
     * Set the ticket's status.
     */
    public function withStatus(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status->value,
        ]);
    }
}
