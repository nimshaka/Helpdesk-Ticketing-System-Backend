<?php

namespace App\Http\Controllers;

use App\Handlers\Dashboard\GetDashboardHandler;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(ShowDashboardRequest $request, GetDashboardHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            $handler->handle($request->user()),
            'Dashboard retrieved.',
        );
    }
}
