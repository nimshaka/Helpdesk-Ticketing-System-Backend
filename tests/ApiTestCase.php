<?php

namespace Tests;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Mail::fake();
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole(UserRole::Admin, $attributes);
    }

    protected function agent(array $attributes = []): User
    {
        return $this->userWithRole(UserRole::Agent, $attributes);
    }

    protected function assertErrorShape(TestResponse $response, int $status): void
    {
        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', $status)
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    protected function asUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function customer(array $attributes = []): User
    {
        return $this->userWithRole(UserRole::Customer, $attributes);
    }

    protected function heldTicket(
        User $customer,
        User $agent,
        TicketStatus $status = TicketStatus::InProgress,
        array $attributes = [],
    ): Ticket {
        $ticket = $this->ticketFor($customer, $status, $attributes);

        $ticket->agents()->attach($agent->id, [
            'assigned_by' => $agent->id,
            'assigned_at' => now(),
        ]);

        return $ticket;
    }

    protected function ticketFor(User $customer, TicketStatus $status = TicketStatus::Open, array $attributes = []): Ticket
    {
        return Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status->value,
            ...$attributes,
        ]);
    }

    protected function userWithRole(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}