<?php

namespace App\Http\Controllers;

use App\Handlers\Auth\LoginHandler;
use App\Handlers\Auth\LogoutHandler;
use App\Handlers\Auth\RegisterHandler;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginHandler $handler): JsonResponse
    {
        $result = $handler->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        return ApiResponse::success($this->tokenPayload($result), 'Login successful.');
    }

    public function logout(Request $request, LogoutHandler $handler): JsonResponse
    {
        $handler->handle($request->user());

        return ApiResponse::success(null, 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('roles')),
            'Authenticated user.',
        );
    }

    public function register(RegisterRequest $request, RegisterHandler $handler): JsonResponse
    {
        $result = $handler->handle($request->validated());

        return ApiResponse::success($this->tokenPayload($result), 'Registration successful.', 201);
    }

    /**
     * @param  array{token: string, user: \App\Models\User}  $result
     * @return array<string, mixed>
     */
    private function tokenPayload(array $result): array
    {
        return [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ];
    }
}