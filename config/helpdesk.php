<?php

return [

    /*
    | The mailbox that receives a notification whenever a customer creates a ticket.
    */
    'support_email' => env('HELPDESK_SUPPORT_EMAIL', 'support@helpdesk.test'),

    /*
    | The address of the Next.js application. Used for links inside emails.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

];