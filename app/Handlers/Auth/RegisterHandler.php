<?php

namespace App\Handlers\Auth;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\User;
use App\Services\UserNotificationService;
use App\Services\UserService;

class RegisterHandler
{
    use QueuesNotifications;

    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, user: User}
     */
    public function registerUserHandler(array $data): array
    {
        $result = $this->userService->userRegisterService($data);

        $this->queueMailNotifications($this->userNotifications()->userRegistrationNotificationService($result['user']));

        return [
            'token' => $result['token'],
            'user' => $result['user'],
        ];
    }

    private function userNotifications(): UserNotificationService
    {
        return app(UserNotificationService::class);
    }
}
