<?php

return [

    /*
    | The address of the Next.js application. Used for links inside emails.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    | A ticket with no update for this many hours counts as stale.
    */
    'reminder_after_hours' => (int) env('HELPDESK_REMINDER_AFTER_HOURS', 24),

    /*
    | When the daily reminder runs, and in which timezone.
    */
    'reminder_time' => env('HELPDESK_REMINDER_TIME', '09:00'),
    'reminder_timezone' => env('HELPDESK_REMINDER_TIMEZONE', 'Asia/Colombo'),

    /*
    | The mailbox that receives a notification whenever a customer creates a ticket.
    */
    'support_email' => env('HELPDESK_SUPPORT_EMAIL', 'support@helpdesk.test'),

    /*
    | Ticket attachments: where files are stored, how many, how big, which types,
    | and how long a signed link stays valid.
    */
    'attachment_disk' => env('HELPDESK_ATTACHMENT_DISK', 'r2'),
    'attachment_max_files' => 5,
    'attachment_max_kb' => 5120,
    'attachment_types' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    'attachment_url_minutes' => (int) env('HELPDESK_ATTACHMENT_URL_MINUTES', 10),

];
