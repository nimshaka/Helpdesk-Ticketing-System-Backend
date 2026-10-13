<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'assignment' => ['nullable', Rule::in(['unassigned', 'mine'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
