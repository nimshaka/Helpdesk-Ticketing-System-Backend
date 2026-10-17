<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class TicketAssignmentTest extends ApiTestCase
{
    public function test_admin_assigns_an_open_ticket_and_it_moves_to_in_progress(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $ticket = $this->ticketFor($this->customer());

        $this->asUser($admin)->patchJson("/api/tickets/{$ticket->id}/assign", ['agent_id' => $agent->id])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress')
            ->assertJsonPath('data.agent.id', $agent->id);

        $this->assertDatabaseHas('ticket_user', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'assigned_by' => $admin->id,
            'unassigned_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'open',
            'to_status' => 'in_progress',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_agent_claims_an_open_ticket(): void
    {
        $agent = $this->agent();
        $ticket = $this->ticketFor($this->customer(), attributes: ['last_activity_at' => now()->subDays(2)]);

        $this->asUser($agent)->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress')
            ->assertJsonPath('data.agent.id', $agent->id);

        $this->assertDatabaseHas('ticket_user', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'assigned_by' => $agent->id,
            'unassigned_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'open',
            'to_status' => 'in_progress',
            'changed_by' => $agent->id,
        ]);
        $this->assertTrue($ticket->fresh()->last_activity_at->greaterThan(now()->subMinute()));
    }

    public function test_assign_refuses_invalid_targets_and_states(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticketFor($this->customer());
        $url = "/api/tickets/{$ticket->id}/assign";
        $this->asUser($admin);

        $this->assertErrorShape($this->patchJson($url, []), 422);
        $this->assertErrorShape($this->patchJson($url, ['agent_id' => 9999]), 422);
        $this->assertErrorShape($this->patchJson($url, ['agent_id' => $this->customer()->id]), 422);
        $this->assertErrorShape($this->patchJson($url, ['agent_id' => $this->agent(['is_active' => false])->id]), 422);

        $resolved = $this->ticketFor($this->customer(), TicketStatus::Resolved);
        $response = $this->patchJson("/api/tickets/{$resolved->id}/assign", ['agent_id' => $this->agent()->id]);
        $this->assertErrorShape($response, 409);
        $response->assertJsonPath('error.message', 'Resolved tickets cannot be assigned.');
    }

    public function test_assigning_the_same_agent_again_is_a_conflict(): void
    {
        $agent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $agent);

        $response = $this->asUser($this->admin())->patchJson("/api/tickets/{$ticket->id}/assign", ['agent_id' => $agent->id]);

        $this->assertErrorShape($response, 409);
        $response->assertJsonPath('error.message', 'The ticket is already assigned to this agent.');
    }

    public function test_claim_conflicts_leave_the_data_unchanged(): void
    {
        $first = $this->agent();
        $second = $this->agent();
        $held = $this->heldTicket($this->customer(), $first);
        $resolved = $this->ticketFor($this->customer(), TicketStatus::Resolved);

        $taken = $this->asUser($second)->postJson("/api/tickets/{$held->id}/claim");
        $this->assertErrorShape($taken, 409);
        $taken->assertJsonPath('error.message', 'This ticket has already been assigned.');

        $closed = $this->postJson("/api/tickets/{$resolved->id}/claim");
        $this->assertErrorShape($closed, 409);
        $closed->assertJsonPath('error.message', 'Only open tickets can be claimed.');

        $this->assertSame(1, DB::table('ticket_user')->count());
        $this->assertDatabaseCount('ticket_status_histories', 0);
    }

    public function test_only_agents_can_claim_and_only_admins_can_assign(): void
    {
        $ticket = $this->ticketFor($this->customer());
        $claim = "/api/tickets/{$ticket->id}/claim";
        $assign = "/api/tickets/{$ticket->id}/assign";

        $this->postJson($claim)->assertStatus(401);
        $this->assertErrorShape($this->asUser($this->customer())->postJson($claim), 403);
        $this->assertErrorShape($this->asUser($this->admin())->postJson($claim), 403);
        $this->assertErrorShape($this->asUser($this->agent())->patchJson($assign, ['agent_id' => 1]), 403);
        $this->assertErrorShape($this->asUser($this->agent())->postJson('/api/tickets/9999/claim'), 404);
    }

    public function test_reassigning_closes_the_old_row_and_keeps_one_current_agent(): void
    {
        $oldAgent = $this->agent();
        $newAgent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $oldAgent);

        $this->asUser($this->admin())->patchJson("/api/tickets/{$ticket->id}/assign", ['agent_id' => $newAgent->id])
            ->assertOk()
            ->assertJsonPath('data.agent.id', $newAgent->id)
            ->assertJsonPath('data.status.value', 'in_progress');

        $this->assertNotNull(DB::table('ticket_user')->where('ticket_id', $ticket->id)->where('user_id', $oldAgent->id)->value('unassigned_at'));
        $this->assertSame(1, DB::table('ticket_user')->where('ticket_id', $ticket->id)->whereNull('unassigned_at')->count());
        $this->assertSame(2, DB::table('ticket_user')->where('ticket_id', $ticket->id)->count());
        $this->assertSame(0, $ticket->statusHistories()->count());
    }

    public function test_reassigning_a_waiting_ticket_keeps_its_status(): void
    {
        $ticket = $this->heldTicket($this->customer(), $this->agent(), TicketStatus::WaitingForCustomer);

        $this->asUser($this->admin())->patchJson("/api/tickets/{$ticket->id}/assign", ['agent_id' => $this->agent()->id])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'waiting_for_customer');

        $this->assertSame(0, $ticket->statusHistories()->count());
    }
}