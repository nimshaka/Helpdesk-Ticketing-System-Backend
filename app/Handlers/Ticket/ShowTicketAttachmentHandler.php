<?php

namespace App\Handlers\Ticket;

use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;

class ShowTicketAttachmentHandler
{
    public function __construct(private readonly TicketAttachmentService $ticketAttachmentService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function showTicketAttachmentHandler(TicketAttachment $attachment, bool $download): array
    {
        return [
            ...$this->ticketAttachmentService->temporaryAttachmentUrlService($attachment, $download),
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'download' => $download,
        ];
    }
}