<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Tests\ApiTestCase;

class TicketCreateTest extends ApiTestCase
{
    public function test_customer_creates_an_open_unassigned_ticket_with_a_history_row(): void
    {
        $customer = $this->customer();

        $response = $this->asUser($customer)->postJson('/api/tickets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.customer.id', $customer->id);

        $ticket = Ticket::findOrFail($response->json('data.id'));

        $this->assertNotNull($ticket->last_activity_at);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => 'open',
            'changed_by' => $customer->id,
        ]);
        $this->assertDatabaseCount('ticket_user', 0);
    }

    public function test_only_customers_can_create_tickets(): void
    {
        $this->postJson('/api/tickets', $this->payload())->assertStatus(401);
        $this->assertErrorShape($this->asUser($this->agent())->postJson('/api/tickets', $this->payload()), 403);
        $this->assertErrorShape($this->asUser($this->admin())->postJson('/api/tickets', $this->payload()), 403);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_status_owner_and_id_cannot_be_chosen_by_the_client(): void
    {
        $customer = $this->customer();
        $other = $this->customer();

        $response = $this->asUser($customer)->postJson('/api/tickets', $this->payload([
            'status' => 'resolved',
            'customer_id' => $other->id,
            'id' => 999,
        ]))->assertCreated();

        $response->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.customer.id', $customer->id);
        $this->assertNotSame(999, $response->json('data.id'));
    }

    public function test_validation_errors_use_the_standard_shape_and_save_nothing(): void
    {
        $this->asUser($this->customer());

        $empty = $this->postJson('/api/tickets', []);
        $this->assertErrorShape($empty, 422);
        $empty->assertJsonStructure(['error' => ['details' => ['subject', 'description', 'priority']]]);

        $this->assertErrorShape($this->postJson('/api/tickets', $this->payload(['priority' => 'urgent'])), 422);
        $this->assertErrorShape($this->postJson('/api/tickets', $this->payload(['subject' => str_repeat('a', 256)])), 422);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_status_histories', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'subject' => 'Printer is not working',
            'description' => 'It shows error E-12.',
            'priority' => 'high',
            ...$overrides,
        ];
    }
}
