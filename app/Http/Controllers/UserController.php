<?php

namespace App\Http\Controllers;

use App\Handlers\User\ActivateUserHandler;
use App\Handlers\User\CreateUserHandler;
use App\Handlers\User\DeactivateUserHandler;
use App\Handlers\User\ListUsersHandler;
use App\Handlers\User\UpdateUserHandler;
use App\Handlers\User\UpdateUserRoleHandler;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function activateUserController(User $user, ActivateUserHandler $handler): JsonResponse
    {
        return ApiResponse::success(new UserResource($handler->activateUserHandler($user)), 'User activated.');
    }

    public function deactivateUserController(Request $request, User $user, DeactivateUserHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($handler->deactivateUserHandler($user, $request->user())),
            'User deactivated.',
        );
    }

    public function indexUserController(ListUsersRequest $request, ListUsersHandler $handler): JsonResponse
    {
        return ApiResponse::paginated(
            $handler->listUsersHandler($request->validated()),
            UserResource::class,
            'Users retrieved.',
        );
    }

    public function showUserController(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user), 'User retrieved.');
    }

    public function storeUserController(StoreUserRequest $request, CreateUserHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($handler->createUserHandler($request->validated())),
            'User created.',
            201,
        );
    }

    public function updateUserController(UpdateUserRequest $request, User $user, UpdateUserHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($handler->updateUserHandler($user, $request->validated())),
            'User updated.',
        );
    }

    public function updateRoleUserController(UpdateUserRoleRequest $request, User $user, UpdateUserRoleHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($handler->updateUserRoleHandler($user, $request->user(), $request->validated('role'))),
            'User role updated.',
        );
    }
}
