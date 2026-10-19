<?php

namespace App\Handlers\Dashboard;

use App\Models\User;
use App\Services\DashboardService;

class GetDashboardHandler
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardHandler(User $user): array
    {
        return $this->dashboardService->getDashboardService($user);
    }
}
