<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RoleCheckController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('role-checks')->group(function () {
    Route::get('admin', [RoleCheckController::class, 'admin'])->middleware('role:admin');
    Route::get('agent', [RoleCheckController::class, 'agent'])->middleware('role:agent|admin');
    Route::get('customer', [RoleCheckController::class, 'customer'])->middleware('role:customer');
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
