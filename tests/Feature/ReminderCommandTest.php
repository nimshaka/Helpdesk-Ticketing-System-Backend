<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Mail\AgentReminderMail;
use App\Mail\CustomerReminderMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

class ReminderCommandTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 10, 14, 8));
    }

    public function test_a_waiting_ticket_reminds_the_customer(): void
    {
        $customer = $this->customer();
        $this->heldTicket($customer, $this->agent(), TicketStatus::WaitingForCustomer);

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).')->assertSuccessful();

        Mail::assertQueued(CustomerReminderMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertQueuedCount(1);
    }

    public function test_a_stale_in_progress_ticket_reminds_the_agent(): void
    {
        $agent = $this->agent();
        $this->heldTicket($this->customer(), $agent);

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).')->assertSuccessful();

        Mail::assertQueued(AgentReminderMail::class, fn ($mail) => $mail->hasTo($agent->email));
        Mail::assertQueuedCount(1);
    }

    public function test_a_deactivated_agent_is_not_emailed_and_the_ticket_is_retried(): void
    {
        $agent = $this->agent(['is_active' => false]);
        $ticket = $this->heldTicket($this->customer(), $agent);

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        Mail::assertNothingQueued();
        $this->assertNull($ticket->fresh()->last_reminded_at);

        $agent->update(['is_active' => true]);
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');
        $this->assertNotNull($ticket->fresh()->last_reminded_at);
    }

    public function test_a_ticket_is_reminded_again_the_next_day(): void
    {
        $this->heldTicket($this->customer(), $this->agent());

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');

        $this->travel(1)->days();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');

        Mail::assertQueuedCount(2);
    }

    public function test_a_ticket_is_reminded_at_most_once_per_day(): void
    {
        $this->heldTicket($this->customer(), $this->agent());

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        Mail::assertQueuedCount(1);
    }

    public function test_an_in_progress_ticket_without_an_agent_is_skipped(): void
    {
        $this->ticketFor($this->customer(), TicketStatus::InProgress);

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        Mail::assertNothingQueued();
    }

    public function test_open_and_resolved_tickets_are_never_reminded(): void
    {
        $customer = $this->customer();
        $this->ticketFor($customer, TicketStatus::Open);
        $this->ticketFor($customer, TicketStatus::Resolved);

        $this->travel(72)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        Mail::assertNothingQueued();
    }

    public function test_reminders_do_not_count_as_activity(): void
    {
        $ticket = $this->heldTicket($this->customer(), $this->agent());
        $original = $ticket->fresh()->last_activity_at;

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->assertSuccessful();

        $ticket = $ticket->fresh();

        $this->assertTrue($ticket->last_activity_at->equalTo($original));
        $this->assertNotNull($ticket->last_reminded_at);
    }

    public function test_the_24_hour_boundary_is_included(): void
    {
        $this->heldTicket($this->customer(), $this->agent());

        $this->travel(23)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        $this->travel(1)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');
    }

    public function test_the_number_of_hours_comes_from_the_configuration(): void
    {
        config(['helpdesk.reminder_after_hours' => 48]);
        $this->heldTicket($this->customer(), $this->agent());

        $this->travel(25)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 0 ticket reminder(s).');

        $this->travel(24)->hours();
        $this->artisan('tickets:send-reminders')->expectsOutput('Queued 1 ticket reminder(s).');
    }

    public function test_the_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('tickets:send-reminders')->assertSuccessful();
    }
}