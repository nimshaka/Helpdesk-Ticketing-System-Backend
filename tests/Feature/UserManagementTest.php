<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\ApiTestCase;

class UserManagementTest extends ApiTestCase
{
    public function test_activate_and_missing_user(): void
    {
        $this->asUser($this->admin());
        $inactive = $this->customer(['is_active' => false]);

        $this->patchJson("/api/users/{$inactive->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertErrorShape($this->getJson('/api/users/9999'), 404);
    }

    public function test_admin_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = $this->admin();
        $this->asUser($admin);

        $deactivate = $this->patchJson("/api/users/{$admin->id}/deactivate");
        $this->assertErrorShape($deactivate, 403);
        $deactivate->assertJsonPath('error.message', 'You cannot deactivate your own account.');

        $role = $this->patchJson("/api/users/{$admin->id}/role", ['role' => 'customer']);
        $this->assertErrorShape($role, 403);
        $role->assertJsonPath('error.message', 'You cannot change your own role.');

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole(UserRole::Admin));
    }

    public function test_admin_creates_a_user_with_exactly_one_role(): void
    {
        $payload = [
            'name' => 'New Agent',
            'email' => 'new.agent@helpdesk.test',
            'password' => 'password123',
            'role' => 'agent',
        ];

        $this->asUser($this->admin())->postJson('/api/users', $payload)
            ->assertCreated()
            ->assertJsonPath('data.role', 'agent');

        $user = User::where('email', 'new.agent@helpdesk.test')->firstOrFail();

        $this->assertSame(1, $user->roles()->count());
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue($user->is_active);
    }

    public function test_admin_lists_users_with_pagination_and_filters(): void
    {
        $this->asUser($this->admin());
        $this->agent();
        $this->agent();
        $this->customer(['name' => 'Zebra Tester']);

        $this->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/users?role=agent')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/users?search=zebra')->assertJsonPath('meta.total', 1);
        $this->assertErrorShape($this->getJson('/api/users?role=boss'), 422);
    }

    public function test_create_user_validates_its_input(): void
    {
        $existing = $this->customer();
        $this->asUser($this->admin());
        $valid = ['name' => 'Someone', 'email' => 'someone@helpdesk.test', 'password' => 'password123', 'role' => 'agent'];

        $this->assertErrorShape($this->postJson('/api/users', [...$valid, 'email' => $existing->email]), 422);
        $this->assertErrorShape($this->postJson('/api/users', [...$valid, 'role' => 'boss']), 422);
        $this->assertErrorShape($this->postJson('/api/users', [...$valid, 'password' => 'short']), 422);
        $this->assertDatabaseMissing('users', ['email' => 'someone@helpdesk.test']);
    }

    public function test_deactivating_a_user_deletes_their_tokens(): void
    {
        $target = $this->agent();
        $target->createToken('api');
        $this->assertSame(1, $target->tokens()->count());

        $this->asUser($this->admin())->patchJson("/api/users/{$target->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_only_admins_can_use_the_users_api(): void
    {
        $this->getJson('/api/users')->assertStatus(401);

        foreach ([$this->customer(), $this->agent()] as $user) {
            $this->assertErrorShape($this->asUser($user)->getJson('/api/users'), 403);
            $this->asUser($user)->postJson('/api/users', [])->assertForbidden();
            $this->asUser($user)->patchJson("/api/users/{$user->id}", [])->assertForbidden();
        }
    }

    public function test_set_role_replaces_the_role_and_keeps_exactly_one(): void
    {
        $target = $this->customer();

        $this->asUser($this->admin())->patchJson("/api/users/{$target->id}/role", ['role' => 'agent'])
            ->assertOk()
            ->assertJsonPath('data.role', 'agent');

        $target = $target->fresh();

        $this->assertSame(1, $target->roles()->count());
        $this->assertTrue($target->hasRole(UserRole::Agent));
    }

    public function test_update_changes_only_name_and_email(): void
    {
        $target = $this->customer(['name' => 'Old Name']);
        $this->asUser($this->admin());

        $this->patchJson("/api/users/{$target->id}", ['name' => 'New Name', 'password' => 'hacked12345', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $target = $target->fresh();

        $this->assertTrue(Hash::check('password', $target->password));
        $this->assertFalse(Hash::check('hacked12345', $target->password));
        $this->assertTrue($target->hasRole(UserRole::Customer));
        $this->assertFalse($target->hasRole(UserRole::Admin));

        $this->assertErrorShape($this->patchJson("/api/users/{$target->id}", []), 422);
        $this->patchJson("/api/users/{$target->id}", ['email' => $target->email])->assertOk();
        $this->assertErrorShape($this->patchJson("/api/users/{$target->id}", ['email' => $this->admin()->email]), 422);
    }
}
