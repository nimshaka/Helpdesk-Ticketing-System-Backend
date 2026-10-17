<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Services\TicketWorkflowService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketWorkflowServiceTest extends TestCase
{
    /**
     * @return array<string, array{TicketStatus, TicketStatus}>
     */
    public static function invalidMoves(): array
    {
        $valid = array_values(self::validMoves());
        $cases = [];

        foreach (TicketStatus::cases() as $from) {
            foreach (TicketStatus::cases() as $to) {
                if (in_array([$from, $to], $valid, true)) {
                    continue;
                }

                $cases["{$from->value} to {$to->value}"] = [$from, $to];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{TicketStatus, TicketStatus}>
     */
    public static function validMoves(): array
    {
        return [
            'open to in progress' => [TicketStatus::Open, TicketStatus::InProgress],
            'in progress to waiting' => [TicketStatus::InProgress, TicketStatus::WaitingForCustomer],
            'in progress to resolved' => [TicketStatus::InProgress, TicketStatus::Resolved],
            'waiting to in progress' => [TicketStatus::WaitingForCustomer, TicketStatus::InProgress],
            'waiting to resolved' => [TicketStatus::WaitingForCustomer, TicketStatus::Resolved],
        ];
    }

    public function test_only_resolved_tickets_refuse_replies(): void
    {
        $service = new TicketWorkflowService;

        $this->assertTrue($service->acceptsReplies(TicketStatus::Open));
        $this->assertTrue($service->acceptsReplies(TicketStatus::InProgress));
        $this->assertTrue($service->acceptsReplies(TicketStatus::WaitingForCustomer));
        $this->assertFalse($service->acceptsReplies(TicketStatus::Resolved));
    }

    #[DataProvider('invalidMoves')]
    public function test_invalid_moves_are_refused(TicketStatus $from, TicketStatus $to): void
    {
        $this->assertFalse((new TicketWorkflowService)->canTransition($from, $to));
    }

    public function test_only_waiting_tickets_change_status_after_a_customer_reply(): void
    {
        $service = new TicketWorkflowService;

        $this->assertSame(TicketStatus::InProgress, $service->statusAfterCustomerReply(TicketStatus::WaitingForCustomer));
        $this->assertNull($service->statusAfterCustomerReply(TicketStatus::Open));
        $this->assertNull($service->statusAfterCustomerReply(TicketStatus::InProgress));
        $this->assertNull($service->statusAfterCustomerReply(TicketStatus::Resolved));
    }

    #[DataProvider('validMoves')]
    public function test_valid_moves_are_allowed(TicketStatus $from, TicketStatus $to): void
    {
        $this->assertTrue((new TicketWorkflowService)->canTransition($from, $to));
    }
}