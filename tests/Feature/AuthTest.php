<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function test_deactivated_user_cannot_log_in(): void
    {
        $this->customer(['email' => 'sara@helpdesk.test', 'is_active' => false]);

        $response = $this->postJson('/api/auth/login', ['email' => 'sara@helpdesk.test', 'password' => 'password']);

        $this->assertErrorShape($response, 403);
        $response->assertJsonPath('error.message', 'Your account has been deactivated.');
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $payload = ['email' => 'nobody@helpdesk.test', 'password' => 'wrong'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', $payload)->assertStatus(401);
        }

        $this->assertErrorShape($this->postJson('/api/auth/login', $payload), 429);
    }

    public function test_login_validates_its_input(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $this->assertErrorShape($response, 422);
        $response->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
    }

    public function test_logout_deletes_the_token_so_it_stops_working(): void
    {
        $this->customer(['email' => 'sara@helpdesk.test']);
        $token = $this->postJson('/api/auth/login', ['email' => 'sara@helpdesk.test', 'password' => 'password'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->assertErrorShape($this->withToken($token)->getJson('/api/auth/me'), 401);
    }

    public function test_me_requires_a_token_and_returns_the_current_user(): void
    {
        $this->assertErrorShape($this->getJson('/api/auth/me'), 401);

        $agent = $this->agent();

        $this->asUser($agent)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $agent->email)
            ->assertJsonPath('data.role', 'agent');
    }

    public function test_register_creates_a_customer_and_ignores_a_requested_role(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Nimal Perera',
            'email' => 'nimal@helpdesk.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('data.user.role', 'customer');

        $user = User::where('email', 'nimal@helpdesk.test')->firstOrFail();

        $this->assertTrue($user->hasRole(UserRole::Customer));
        $this->assertFalse($user->hasRole(UserRole::Admin));
    }

    public function test_register_rejects_bad_input_and_saves_nothing(): void
    {
        $this->customer(['email' => 'taken@helpdesk.test']);
        $valid = [
            'name' => 'Nimal',
            'email' => 'nimal@helpdesk.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->assertErrorShape($this->postJson('/api/auth/register', [...$valid, 'email' => 'taken@helpdesk.test']), 422);
        $this->assertErrorShape($this->postJson('/api/auth/register', [...$valid, 'password_confirmation' => 'different']), 422);
        $this->assertErrorShape($this->postJson('/api/auth/register', [...$valid, 'password' => 'short', 'password_confirmation' => 'short']), 422);
        $this->assertDatabaseMissing('users', ['email' => 'nimal@helpdesk.test']);
    }

    public function test_unknown_email_and_wrong_password_get_the_same_401(): void
    {
        $this->customer(['email' => 'sara@helpdesk.test']);

        $wrongPassword = $this->postJson('/api/auth/login', ['email' => 'sara@helpdesk.test', 'password' => 'wrong']);
        $unknownEmail = $this->postJson('/api/auth/login', ['email' => 'nobody@helpdesk.test', 'password' => 'wrong']);

        $this->assertErrorShape($wrongPassword, 401);
        $wrongPassword->assertJsonPath('error.message', 'Invalid email or password.');
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_user_can_log_in_and_receives_a_bearer_token(): void
    {
        $this->customer(['email' => 'sara@helpdesk.test']);

        $this->postJson('/api/auth/login', ['email' => 'sara@helpdesk.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'is_active']]]);
    }
}