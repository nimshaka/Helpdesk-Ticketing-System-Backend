<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleCheckController extends Controller
{
    public function admin(): JsonResponse
    {
        return ApiResponse::success(null, 'Admin access granted.');
    }

    public function agent(): JsonResponse
    {
        return ApiResponse::success(null, 'Agent access granted.');
    }

    public function customer(): JsonResponse
    {
        return ApiResponse::success(null, 'Customer access granted.');
    }
}