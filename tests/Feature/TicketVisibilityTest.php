<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\ApiTestCase;

class TicketVisibilityTest extends ApiTestCase
{
    private User $adminUser;

    private User $agentOne;

    private User $agentTwo;

    private User $customerOne;

    private User $customerTwo;

    private Ticket $heldOne;

    private Ticket $heldTwo;

    private Ticket $openOne;

    private Ticket $openTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->admin();
        $this->agentOne = $this->agent();
        $this->agentTwo = $this->agent();
        $this->customerOne = $this->customer();
        $this->customerTwo = $this->customer();

        $this->openOne = $this->ticketFor($this->customerOne);
        $this->openTwo = $this->ticketFor($this->customerTwo);
        $this->heldOne = $this->heldTicket($this->customerOne, $this->agentOne);
        $this->heldTwo = $this->heldTicket($this->customerTwo, $this->agentTwo);
    }

    public function test_admin_sees_every_ticket_and_the_assignment_history(): void
    {
        $this->asUser($this->adminUser)->getJson('/api/tickets')->assertJsonPath('meta.total', 4);

        $this->getJson("/api/tickets/{$this->heldOne->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['assignments']]);
    }

    public function test_agent_sees_unassigned_tickets_and_their_own_but_not_other_agents(): void
    {
        $response = $this->asUser($this->agentOne)->getJson('/api/tickets');

        $this->assertSame(
            [$this->openOne->id, $this->openTwo->id, $this->heldOne->id],
            $this->listedIds($response),
        );

        $this->getJson("/api/tickets/{$this->openTwo->id}")->assertOk();
        $this->getJson("/api/tickets/{$this->heldOne->id}")->assertOk();
        $this->assertErrorShape($this->getJson("/api/tickets/{$this->heldTwo->id}"), 404);
    }

    public function test_customer_sees_only_their_own_tickets(): void
    {
        $response = $this->asUser($this->customerOne)->getJson('/api/tickets');

        $this->assertSame([$this->openOne->id, $this->heldOne->id], $this->listedIds($response));
        $this->getJson("/api/tickets/{$this->heldOne->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.assignments');
    }

    public function test_customer_cannot_widen_the_list_with_a_customer_id_filter(): void
    {
        $this->asUser($this->customerOne)
            ->getJson("/api/tickets?customer_id={$this->customerTwo->id}")
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filters_combine_and_invalid_values_are_rejected(): void
    {
        $this->openOne->update(['subject' => 'Printer broken']);
        $this->asUser($this->adminUser);

        $this->getJson('/api/tickets?status=open')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/tickets?assignment=unassigned')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/tickets?search=printer')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tickets?status=open&search=printer')->assertJsonPath('meta.total', 1);

        $this->assertErrorShape($this->getJson('/api/tickets?status=banana'), 422);
        $this->assertErrorShape($this->getJson('/api/tickets?per_page=500'), 422);
        $this->assertErrorShape($this->getJson('/api/tickets?assignment=nobody'), 422);

        $this->asUser($this->agentOne)->getJson('/api/tickets?assignment=mine')->assertJsonPath('meta.total', 1);
    }

    public function test_forbidden_and_missing_tickets_return_the_identical_404(): void
    {
        $this->asUser($this->customerTwo);

        $forbidden = $this->getJson("/api/tickets/{$this->heldOne->id}");
        $missing = $this->getJson('/api/tickets/9999');

        $this->assertErrorShape($forbidden, 404);
        $this->assertSame($missing->json(), $forbidden->json());
    }

    public function test_pagination_meta_is_correct(): void
    {
        Ticket::factory()->count(20)->create(['customer_id' => $this->customerOne->id]);

        $this->asUser($this->adminUser)->getJson('/api/tickets?per_page=10&page=2')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 24)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_replies_come_back_in_chronological_order(): void
    {
        TicketReply::factory()->create([
            'ticket_id' => $this->heldOne->id,
            'user_id' => $this->agentOne->id,
            'body' => 'second',
            'created_at' => now(),
        ]);
        TicketReply::factory()->create([
            'ticket_id' => $this->heldOne->id,
            'user_id' => $this->customerOne->id,
            'body' => 'first',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->asUser($this->customerOne)->getJson("/api/tickets/{$this->heldOne->id}");

        $this->assertSame(['first', 'second'], collect($response->json('data.replies'))->pluck('body')->all());
    }

    /**
     * @return list<int>
     */
    private function listedIds(TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->sort()->values()->all();
    }
}
