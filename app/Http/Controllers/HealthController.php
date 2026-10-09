<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Support\ApiResponse;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
       return ApiResponse::success(['status' => 'ok'], 'API is running well.');
    }
}
