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
    Route::post('login', [AuthController::class, 'loginUserController'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'registerUserController']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logoutUserController']);
        Route::get('me', [AuthController::class, 'meUserController']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'indexUserController']);
    Route::post('/', [UserController::class, 'storeUserController']);
    Route::get('{user}', [UserController::class, 'showUserController']);
    Route::patch('{user}', [UserController::class, 'updateUserController']);
    Route::patch('{user}/activate', [UserController::class, 'activateUserController']);
    Route::patch('{user}/deactivate', [UserController::class, 'deactivateUserController']);
    Route::patch('{user}/role', [UserController::class, 'updateRoleUserController']);
});

Route::get('dashboard', [DashboardController::class, 'indexDashboardController'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'indexTicketController']);
    Route::get('{ticket}', [TicketController::class, 'showTicketController'])->whereNumber('ticket');
    Route::patch('{ticket}/assign', [TicketController::class, 'assignTicketController'])->whereNumber('ticket')->middleware('role:admin');
    Route::post('/', [TicketController::class, 'storeTicketController'])->middleware('role:customer');
    Route::post('{ticket}/claim', [TicketController::class, 'claimTicketController'])->whereNumber('ticket')->middleware('role:agent');
    Route::post('{ticket}/replies', [TicketReplyController::class, 'storeTicketReplyController'])->whereNumber('ticket');
    Route::patch('{ticket}/status', [TicketController::class, 'updateStatusTicketController'])->whereNumber('ticket')->middleware('role:admin|agent');
});
