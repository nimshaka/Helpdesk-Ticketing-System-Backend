<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createUser('Admin User', 'admin@helpdesk.test', UserRole::Admin);

        foreach (range(1, 3) as $number) {
            $this->createUser("Agent {$number}", "agent{$number}@helpdesk.test", UserRole::Agent);
        }

        foreach (range(1, 5) as $number) {
            $this->createUser("Customer {$number}", "customer{$number}@helpdesk.test", UserRole::Customer);
        }
    }

    private function createUser(string $name, string $email, UserRole $role): void
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $user->assignRole($role);
    }
}
