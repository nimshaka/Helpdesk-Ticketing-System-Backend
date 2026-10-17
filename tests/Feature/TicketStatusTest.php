<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\ApiTestCase;

class TicketStatusTest extends ApiTestCase
{
    public function test_admin_can_change_the_status_of_any_ticket(): void
    {
        $ticket = $this->heldTicket($this->customer(), $this->agent());

        $this->setStatus($this->admin(), $ticket, 'resolved')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'resolved');
    }

    public function test_agent_who_does_not_hold_a_visible_ticket_gets_a_403(): void
    {
        $ticket = $this->ticketFor($this->customer());

        $response = $this->setStatus($this->agent(), $ticket, 'waiting_for_customer');

        $this->assertErrorShape($response, 403);
        $response->assertJsonPath('error.message', 'Only the assigned agent or an admin can change the status.');
    }

    public function test_agent_who_cannot_see_the_ticket_gets_a_404(): void
    {
        $ticket = $this->heldTicket($this->customer(), $this->agent());

        $this->assertErrorShape($this->setStatus($this->agent(), $ticket, 'resolved'), 404);
    }

    public function test_customers_and_visitors_cannot_change_a_status(): void
    {
        $customer = $this->customer();
        $ticket = $this->heldTicket($customer, $this->agent());

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertStatus(401);
        $this->assertErrorShape($this->setStatus($customer, $ticket, 'resolved'), 403);
        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_holder_moves_in_progress_to_waiting_then_resolved(): void
    {
        $agent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $agent, attributes: ['last_activity_at' => now()->subDays(2)]);

        $this->setStatus($agent, $ticket, 'waiting_for_customer')
            ->assertOk()
            ->assertJsonPath('data.status.value', 'waiting_for_customer');

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'in_progress',
            'to_status' => 'waiting_for_customer',
            'changed_by' => $agent->id,
        ]);
        $this->assertTrue($ticket->fresh()->last_activity_at->greaterThan(now()->subMinute()));

        $this->setStatus($agent, $ticket, 'resolved')->assertOk();

        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);
        $this->assertSame(TicketStatus::Resolved, $ticket->statusHistories()->latest('id')->first()->to_status);
    }

    public function test_illegal_moves_return_409_and_change_nothing(): void
    {
        $admin = $this->admin();
        $open = $this->ticketFor($this->customer());
        $resolved = $this->ticketFor($this->customer(), TicketStatus::Resolved);

        $toResolved = $this->setStatus($admin, $open, 'resolved');
        $this->assertErrorShape($toResolved, 409);
        $toResolved->assertJsonPath('error.message', 'A ticket cannot move from Open to Resolved.');

        $this->assertErrorShape($this->setStatus($admin, $open, 'waiting_for_customer'), 409);
        $this->assertErrorShape($this->setStatus($admin, $resolved, 'waiting_for_customer'), 409);

        $this->assertSame(TicketStatus::Open, $open->fresh()->status);
        $this->assertSame(0, $open->statusHistories()->count());
        $this->assertSame(0, $resolved->statusHistories()->count());
    }

    public function test_the_endpoint_accepts_only_waiting_or_resolved(): void
    {
        $admin = $this->admin();
        $ticket = $this->heldTicket($this->customer(), $this->agent());

        $this->assertErrorShape($this->setStatus($admin, $ticket, 'in_progress'), 422);
        $this->assertErrorShape($this->setStatus($admin, $ticket, 'open'), 422);
        $this->assertErrorShape($this->setStatus($admin, $ticket, 'banana'), 422);
        $this->assertErrorShape($this->asUser($admin)->patchJson("/api/tickets/{$ticket->id}/status", []), 422);
    }

    private function setStatus(User $user, Ticket $ticket, string $status): TestResponse
    {
        return $this->asUser($user)->patchJson("/api/tickets/{$ticket->id}/status", ['status' => $status]);
    }
}
