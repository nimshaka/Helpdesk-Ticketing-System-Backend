<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ErrorResponseTest extends ApiTestCase
{
    public function test_401_has_the_standard_shape_even_without_an_accept_header(): void
    {
        $response = $this->get('/api/auth/me');

        $this->assertErrorShape($response, 401);
        $response->assertJsonPath('error.message', 'Unauthenticated.');
    }

    public function test_403_has_the_standard_shape(): void
    {
        $this->assertErrorShape($this->asUser($this->customer())->getJson('/api/users'), 403);
    }

    public function test_404_for_unknown_routes_and_missing_records(): void
    {
        $this->assertErrorShape($this->getJson('/api/does-not-exist'), 404);
        $this->assertErrorShape($this->asUser($this->admin())->getJson('/api/tickets/9999'), 404);
    }

    public function test_404_never_leaks_internal_class_names(): void
    {
        $content = $this->asUser($this->admin())->getJson('/api/tickets/9999')->getContent();

        $this->assertStringNotContainsString('Symfony', $content);
        $this->assertStringNotContainsString('Illuminate', $content);
        $this->assertStringNotContainsString('vendor/', $content);
    }

    public function test_405_when_the_method_is_wrong(): void
    {
        $response = $this->postJson('/api/health');

        $this->assertErrorShape($response, 405);
        $response->assertJsonPath('error.message', 'Method not allowed.');
    }

    public function test_409_has_the_standard_shape(): void
    {
        $ticket = $this->heldTicket($this->customer(), $this->agent());

        $this->assertErrorShape($this->asUser($this->agent())->postJson("/api/tickets/{$ticket->id}/claim"), 409);
    }

    public function test_422_carries_field_errors_under_details(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $this->assertErrorShape($response, 422);
        $response->assertJsonPath('error.message', 'Validation failed.');
        $this->assertIsArray($response->json('error.details'));
    }

    public function test_429_after_too_many_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'a@helpdesk.test', 'password' => 'x']);
        }

        $this->assertErrorShape($this->postJson('/api/auth/login', ['email' => 'a@helpdesk.test', 'password' => 'x']), 429);
    }
}
