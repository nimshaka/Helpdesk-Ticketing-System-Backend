<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Mail\CustomerRepliedMail;
use App\Mail\NewTicketMail;
use App\Mail\StaffRepliedMail;
use App\Mail\TicketAssignedMail;
use App\Mail\TicketReceivedMail;
use App\Mail\TicketResolvedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

class NotificationTest extends ApiTestCase
{

    public function test_registration_queues_the_welcome_email(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Nimal Perera',
            'email' => 'nimal@helpdesk.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        Mail::assertQueued(\App\Mail\WelcomeMail::class, fn ($mail) => $mail->hasTo('nimal@helpdesk.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_admin_assigning_a_ticket_emails_the_agent(): void
    {
        $agent = $this->agent();
        $ticket = $this->ticketFor($this->customer());

        $this->asUser($this->admin())->patchJson("/api/tickets/{$ticket->id}/assign", ['agent_id' => $agent->id])->assertOk();

        Mail::assertQueued(TicketAssignedMail::class, fn ($mail) => $mail->hasTo($agent->email));
        Mail::assertQueuedCount(1);
    }

    public function test_admin_reply_emails_the_customer_only(): void
    {
        $customer = $this->customer();
        $ticket = $this->heldTicket($customer, $this->agent());

        $this->asUser($this->admin())->postJson("/api/tickets/{$ticket->id}/replies", ['body' => 'From the admin'])->assertCreated();

        Mail::assertQueued(StaffRepliedMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertQueuedCount(1);
    }

    public function test_agent_claiming_a_ticket_sends_no_email(): void
    {
        $ticket = $this->ticketFor($this->customer());

        $this->asUser($this->agent())->postJson("/api/tickets/{$ticket->id}/claim")->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_agent_reply_emails_the_customer(): void
    {
        $customer = $this->customer();
        $agent = $this->agent();
        $ticket = $this->heldTicket($customer, $agent);

        $this->asUser($agent)->postJson("/api/tickets/{$ticket->id}/replies", ['body' => 'We are on it'])->assertCreated();

        Mail::assertQueued(StaffRepliedMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertQueuedCount(1);
    }

    public function test_creating_a_ticket_queues_the_acknowledgement_and_the_support_notification(): void
    {
        $customer = $this->customer();

        $this->asUser($customer)->postJson('/api/tickets', [
            'subject' => 'Printer is not working',
            'description' => 'It shows error E-12.',
            'priority' => 'high',
        ])->assertCreated();

        Mail::assertQueued(TicketReceivedMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertQueued(NewTicketMail::class, fn ($mail) => $mail->hasTo(config('helpdesk.support_email')));
        Mail::assertQueuedCount(2);
    }

    public function test_customer_reply_emails_the_assigned_agent_only(): void
    {
        $customer = $this->customer();
        $agent = $this->agent();
        $ticket = $this->heldTicket($customer, $agent);

        $this->asUser($customer)->postJson("/api/tickets/{$ticket->id}/replies", ['body' => 'Any news?'])->assertCreated();

        Mail::assertQueued(CustomerRepliedMail::class, fn ($mail) => $mail->hasTo($agent->email));
        Mail::assertQueuedCount(1);
    }

    public function test_customer_reply_on_an_unassigned_ticket_emails_nobody(): void
    {
        $customer = $this->customer();
        $ticket = $this->ticketFor($customer);

        $this->asUser($customer)->postJson("/api/tickets/{$ticket->id}/replies", ['body' => 'Hello?'])->assertCreated();

        Mail::assertNothingQueued();
    }

    public function test_every_ticket_mail_is_a_queued_mail(): void
    {
        foreach ([TicketReceivedMail::class, NewTicketMail::class, TicketAssignedMail::class, CustomerRepliedMail::class, StaffRepliedMail::class, TicketResolvedMail::class] as $mail) {
            $this->assertTrue(is_subclass_of($mail, ShouldQueue::class), "{$mail} must implement ShouldQueue");
        }
    }

    

    public function test_refused_requests_queue_nothing(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $resolved = $this->ticketFor($customer, TicketStatus::Resolved);
        $open = $this->ticketFor($customer);

        $this->asUser($customer)->postJson('/api/tickets', [])->assertStatus(422);
        $this->postJson("/api/tickets/{$resolved->id}/replies", ['body' => 'Too late'])->assertStatus(409);
        $this->asUser($admin)->patchJson("/api/tickets/{$open->id}/status", ['status' => 'resolved'])->assertStatus(409);

        Mail::assertNothingQueued();
    }

    public function test_resolving_emails_the_customer_but_waiting_does_not(): void
    {
        $customer = $this->customer();
        $agent = $this->agent();
        $ticket = $this->heldTicket($customer, $agent);

        $this->asUser($agent)->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'waiting_for_customer'])->assertOk();
        Mail::assertNothingQueued();

        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertOk();

        Mail::assertQueued(TicketResolvedMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertQueuedCount(1);
    }
}