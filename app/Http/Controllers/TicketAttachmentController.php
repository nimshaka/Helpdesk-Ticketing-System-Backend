<?php

namespace App\Http\Controllers;

use App\Handlers\Ticket\ShowTicketAttachmentHandler;
use App\Http\Requests\Ticket\ShowTicketAttachmentRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketAttachmentController extends Controller
{
    public function show(
        ShowTicketAttachmentRequest $request,
        Ticket $ticket,
        TicketAttachment $attachment,
        ShowTicketAttachmentHandler $handler,
    ): JsonResponse {
        return ApiResponse::success(
            $handler->showTicketAttachmentHandler($attachment, $request->boolean('download')),
            'Attachment link created.',
        );
    }
}
