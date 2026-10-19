<?php

namespace App\Handlers\Ticket;

use App\Models\User;
use App\Services\TicketService;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTicketsHandler
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listTicketsHandler(User $user, array $filters): LengthAwarePaginator
    {
        return $this->ticketService->listTicketsService($user, $filters);
    }
}
