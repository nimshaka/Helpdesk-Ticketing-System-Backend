<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('{user}', [UserController::class, 'show']);
    Route::patch('{user}', [UserController::class, 'update']);
    Route::patch('{user}/activate', [UserController::class, 'activate']);
    Route::patch('{user}/deactivate', [UserController::class, 'deactivate']);
    Route::patch('{user}/role', [UserController::class, 'updateRole']);
});

Route::get('dashboard', [DashboardController::class, 'index'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index']);
    Route::get('{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
    Route::patch('{ticket}/assign', [TicketController::class, 'assign'])->whereNumber('ticket')->middleware('role:admin');
    Route::post('/', [TicketController::class, 'store'])->middleware('role:customer');
    Route::post('{ticket}/claim', [TicketController::class, 'claim'])->whereNumber('ticket')->middleware('role:agent');
    Route::post('{ticket}/replies', [TicketReplyController::class, 'store'])->whereNumber('ticket');
    Route::patch('{ticket}/status', [TicketController::class, 'updateStatus'])->whereNumber('ticket')->middleware('role:admin|agent');
});
