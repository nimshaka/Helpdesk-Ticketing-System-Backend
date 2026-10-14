<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('changeStatus', $this->route('ticket'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    TicketStatus::WaitingForCustomer->value,
                    TicketStatus::Resolved->value,
                ]),
            ],
        ];
    }

    public function targetStatus(): TicketStatus
    {
        return TicketStatus::from($this->validated('status'));
    }
}
