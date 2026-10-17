<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\TicketReply;
use App\Models\User;
use Tests\ApiTestCase;

class DashboardTest extends ApiTestCase
{
    public function test_activity_feed_has_at_most_ten_items_newest_first(): void
    {
        $agent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $agent);

        foreach (range(1, 12) as $minutes) {
            TicketReply::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $agent->id,
                'created_at' => now()->subMinutes($minutes),
            ]);
        }

        $times = collect($this->asUser($this->admin())->getJson('/api/dashboard')->json('data.recent_activity'))->pluck('occurred_at');

        $this->assertCount(10, $times);
        $this->assertSame($times->sortDesc()->values()->all(), $times->all());
    }

    public function test_admin_counts_everything_and_has_an_unassigned_counter(): void
    {
        $customer = $this->customer();
        $agent = $this->agent();
        $this->ticketFor($customer);
        $this->ticketFor($customer);
        $this->heldTicket($customer, $agent);
        $this->heldTicket($customer, $agent, TicketStatus::WaitingForCustomer);
        $this->heldTicket($customer, $agent, TicketStatus::Resolved);

        $response = $this->asUser($this->admin())->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.unassigned', 2)
            ->assertJsonMissingPath('data.assigned_to_me');

        $counts = collect($response->json('data.counts'))->pluck('count', 'status')->all();

        $this->assertEquals(['open' => 2, 'in_progress' => 1, 'waiting_for_customer' => 1, 'resolved' => 1], $counts);
    }

    public function test_agent_sees_the_queue_plus_their_own_tickets(): void
    {
        $customer = $this->customer();
        $agent = $this->agent();
        $this->ticketFor($customer);
        $this->heldTicket($customer, $agent);
        $this->heldTicket($customer, $this->agent());

        $this->asUser($agent)->getJson('/api/dashboard')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.unassigned', 1)
            ->assertJsonPath('data.assigned_to_me', 1);
    }

    public function test_customer_sees_only_their_own_numbers(): void
    {
        $mine = $this->customer();
        $this->ticketFor($mine);
        $this->ticketFor($this->customer());

        $this->asUser($mine)->getJson('/api/dashboard')
            ->assertJsonPath('data.total', 1)
            ->assertJsonMissingPath('data.unassigned')
            ->assertJsonMissingPath('data.assigned_to_me');
    }

    public function test_dashboard_total_matches_the_ticket_list_for_every_role(): void
    {
        $customerOne = $this->customer();
        $agentOne = $this->agent();
        $this->ticketFor($customerOne);
        $this->ticketFor($this->customer());
        $this->heldTicket($customerOne, $agentOne);
        $this->heldTicket($this->customer(), $this->agent(), TicketStatus::Resolved);

        foreach ([$this->admin(), $agentOne, $customerOne] as $user) {
            $this->asUser($user);

            $this->assertSame(
                $this->getJson('/api/tickets')->json('meta.total'),
                $this->getJson('/api/dashboard')->json('data.total'),
            );
        }
    }

    public function test_feed_never_shows_another_customers_tickets(): void
    {
        $mine = $this->customer();
        $other = $this->customer();
        $myTicket = $this->ticketFor($mine);
        $otherTicket = $this->ticketFor($other);
        TicketReply::factory()->create(['ticket_id' => $myTicket->id, 'user_id' => $mine->id]);
        TicketReply::factory()->create(['ticket_id' => $otherTicket->id, 'user_id' => $other->id]);

        $ids = collect($this->asUser($mine)->getJson('/api/dashboard')->json('data.recent_activity'))->pluck('ticket.id')->unique()->all();

        $this->assertSame([$myTicket->id], $ids);
    }

    public function test_new_customer_gets_zeros_and_an_empty_feed(): void
    {
        $response = $this->asUser($this->customer())->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(4, 'data.counts')
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.recent_activity', []);

        $this->assertSame([0, 0, 0, 0], collect($response->json('data.counts'))->pluck('count')->all());
    }

    public function test_user_without_a_role_is_refused(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);

        $this->assertErrorShape($this->asUser(User::factory()->create())->getJson('/api/dashboard'), 403);
    }
}
