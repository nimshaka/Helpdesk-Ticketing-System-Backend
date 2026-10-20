<?php

namespace App\Handlers\User;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsersHandler
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listUsersHandler(array $filters): LengthAwarePaginator
    {
        return $this->userService->listUsersService($filters);
    }
}
