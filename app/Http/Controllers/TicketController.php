<?php

namespace App\Http\Controllers;

use App\Handlers\Ticket\AssignTicketHandler;
use App\Handlers\Ticket\ClaimTicketHandler;
use App\Handlers\Ticket\CreateTicketHandler;
use App\Handlers\Ticket\ListTicketsHandler;
use App\Handlers\Ticket\ShowTicketHandler;
use App\Handlers\Ticket\UpdateTicketStatusHandler;
use App\Http\Requests\Ticket\AssignTicketRequest;
use App\Http\Requests\Ticket\ClaimTicketRequest;
use App\Http\Requests\Ticket\ListTicketsRequest;
use App\Http\Requests\Ticket\ShowTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function assign(AssignTicketRequest $request, Ticket $ticket, AssignTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $ticket, (int) $request->validated('agent_id'))),
            'Ticket assigned.',
        );
    }

    public function claim(ClaimTicketRequest $request, Ticket $ticket, ClaimTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $ticket)),
            'Ticket claimed.',
        );
    }

    public function index(ListTicketsRequest $request, ListTicketsHandler $handler): JsonResponse
    {
        return ApiResponse::paginated(
            $handler->handle($request->user(), $request->validated()),
            TicketResource::class,
            'Tickets retrieved.',
        );
    }

    public function store(StoreTicketRequest $request, CreateTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $request->validated())),
            'Ticket created.',
            201,
        );
    }

    public function show(ShowTicketRequest $request, Ticket $ticket, ShowTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $ticket)),
            'Ticket retrieved.',
        );
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket, UpdateTicketStatusHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $ticket, $request->targetStatus())),
            'Ticket status updated.',
        );
    }
}
