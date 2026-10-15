<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Mail\Mailable;

final readonly class PendingMail
{
    public function __construct(
        public User|string $recipient,
        public Mailable $mailable,
    ) {
    }
}