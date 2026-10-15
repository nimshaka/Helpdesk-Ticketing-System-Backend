<?php

namespace App\Services;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\PendingMail;

class UserNotificationService
{
    /**
     * Who must be told when a person registers: the person.
     *
     * @return list<PendingMail>
     */
    public function forRegistration(User $user): array
    {
        return [new PendingMail($user, new WelcomeMail($user))];
    }
}