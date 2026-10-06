<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function index(): jsonResponse
    {
      return response()->json([
        'success' => true,
        'data'    => ['status' => 'ok' ],
        'message' => 'API is running',
      ]);
    }
}