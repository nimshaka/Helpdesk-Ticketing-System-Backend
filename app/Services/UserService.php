<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserService
{
    public function activateUserService(User $target): User
    {
        $target->update(['is_active' => true]);

        return $target->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUserService(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles([$data['role']]);

            return $user->load('roles');
        });
    }

    public function deactivateUserService(User $target, User $actor): User
    {
        if ($target->is($actor)) {
            throw new AccessDeniedHttpException('You cannot deactivate your own account.');
        }

        DB::transaction(function () use ($target): void {
            $target->update(['is_active' => false]);
            $target->tokens()->delete();
        });

        return $target->load('roles');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listUsersService(array $filters): LengthAwarePaginator
    {
        $query = User::query()->with('roles');

        if (! empty($filters['search'])) {
            $term = $filters['search'];

            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, user: User}
     */
    public function userRegisterService(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->assignRole(UserRole::Customer);

            return $user;
        });

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('roles'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUserService(User $user, array $data): User
    {
        $user->update(Arr::only($data, ['name', 'email']));

        return $user->load('roles');
    }

    public function updateUserRoleService(User $target, User $actor, string $role): User
    {
        if ($target->is($actor)) {
            throw new AccessDeniedHttpException('You cannot change your own role.');
        }

        $target->syncRoles([$role]);

        return $target->load('roles');
    }
}
