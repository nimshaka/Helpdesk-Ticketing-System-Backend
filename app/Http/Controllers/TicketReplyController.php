<?php

namespace App\Http\Controllers;

use App\Handlers\Ticket\CreateTicketReplyHandler;
use App\Http\Requests\Ticket\StoreTicketReplyRequest;
use App\Http\Resources\TicketReplyResource;
use App\Models\Ticket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketReplyController extends Controller
{
    public function store(StoreTicketReplyRequest $request, Ticket $ticket, CreateTicketReplyHandler $handler): JsonResponse
    {
        $reply = $handler->handle($request->user(), $ticket, $request->validated('body'));

        return ApiResponse::success([
            'reply' => new TicketReplyResource($reply),
            'ticket' => [
                'id' => $reply->ticket->id,
                'status' => [
                    'value' => $reply->ticket->status->value,
                    'label' => $reply->ticket->status->label(),
                ],
            ],
        ], 'Reply added.', 201);
    }
}
