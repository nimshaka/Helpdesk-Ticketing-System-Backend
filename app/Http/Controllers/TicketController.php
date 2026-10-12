<?php

namespace App\Http\Controllers;

use App\Handlers\Ticket\CreateTicketHandler;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function store(StoreTicketRequest $request, CreateTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $request->validated())),
            'Ticket created.',
            201,
        );
    }
}
