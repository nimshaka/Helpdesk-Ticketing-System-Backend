<?php

namespace App\Http\Controllers;

use App\Handlers\Ticket\CreateTicketHandler;
use App\Handlers\Ticket\ListTicketsHandler;
use App\Http\Requests\Ticket\ListTicketsRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use App\Handlers\Ticket\ShowTicketHandler;
use App\Http\Requests\Ticket\ShowTicketRequest;
use App\Models\Ticket;


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
    
    
    public function show(ShowTicketRequest $request, Ticket $ticket, ShowTicketHandler $handler): JsonResponse
    {
        return ApiResponse::success(
            new TicketResource($handler->handle($request->user(), $ticket)),
            'Ticket retrieved.',
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
}
