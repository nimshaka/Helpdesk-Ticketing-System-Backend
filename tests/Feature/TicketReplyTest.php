<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\ApiTestCase;

class TicketReplyTest extends ApiTestCase
{
    public function test_agent_reply_does_not_change_a_waiting_status(): void
    {
        $agent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $agent, TicketStatus::WaitingForCustomer);

        $this->reply($agent, $ticket)
            ->assertCreated()
            ->assertJsonPath('data.ticket.status.value', 'waiting_for_customer');

        $this->assertSame(0, $ticket->statusHistories()->count());
    }

    public function test_agent_must_hold_the_ticket_to_reply(): void
    {
        $ticket = $this->ticketFor($this->customer());

        $response = $this->reply($this->agent(), $ticket);

        $this->assertErrorShape($response, 403);
        $response->assertJsonPath('error.message', 'Claim this ticket before replying.');
        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_customer_reply_to_a_waiting_ticket_returns_it_to_in_progress(): void
    {
        $customer = $this->customer();
        $ticket = $this->heldTicket($customer, $this->agent(), TicketStatus::WaitingForCustomer);

        $this->reply($customer, $ticket, 'Here is the error code.')
            ->assertCreated()
            ->assertJsonPath('data.reply.body', 'Here is the error code.')
            ->assertJsonPath('data.reply.author.role', 'customer')
            ->assertJsonPath('data.ticket.status.value', 'in_progress');

        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'waiting_for_customer',
            'to_status' => 'in_progress',
            'changed_by' => $customer->id,
        ]);
    }

    public function test_customer_reply_on_an_open_ticket_keeps_it_open(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer, attributes: ['last_activity_at' => now()->subDays(2)]);

        $this->reply($customer, $ticket)
            ->assertCreated()
            ->assertJsonPath('data.ticket.status.value', 'open');

        $this->assertTrue($ticket->fresh()->last_activity_at->greaterThan(now()->subMinute()));
    }

    public function test_other_customers_get_a_404_and_the_holder_and_admin_can_reply(): void
    {
        $agent = $this->agent();
        $ticket = $this->heldTicket($this->customer(), $agent);

        $this->assertErrorShape($this->reply($this->customer(), $ticket), 404);
        $this->reply($agent, $ticket)->assertCreated();
        $this->reply($this->admin(), $ticket)->assertCreated();
        $this->assertDatabaseCount('ticket_replies', 2);
    }

    public function test_replies_are_closed_on_resolved_tickets(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer, TicketStatus::Resolved);

        $response = $this->reply($customer, $ticket);

        $this->assertErrorShape($response, 409);
        $response->assertJsonPath('error.message', 'Replies are closed on resolved tickets.');
        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_reply_body_is_validated(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer);
        $this->asUser($customer);

        $this->assertErrorShape($this->postJson("/api/tickets/{$ticket->id}/replies", []), 422);
        $this->assertErrorShape($this->postJson("/api/tickets/{$ticket->id}/replies", ['body' => str_repeat('a', 5001)]), 422);
        $this->postJson("/api/tickets/{$ticket->id}/replies", ['body' => 'Hi'])->assertStatus(201);
    }

    private function reply(User $user, Ticket $ticket, string $body = 'Thanks for the update.'): TestResponse
    {
        return $this->asUser($user)->postJson("/api/tickets/{$ticket->id}/replies", ['body' => $body]);
    }
}
