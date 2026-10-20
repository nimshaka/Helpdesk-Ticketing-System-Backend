<?php

namespace App\Http\Controllers;

use App\Handlers\Dashboard\GetDashboardHandler;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function indexDashboardController(ShowDashboardRequest $request, GetDashboardHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            $handler->getDashboardHandler($request->user()),
            'Dashboard retrieved.',
        );
    }
}
