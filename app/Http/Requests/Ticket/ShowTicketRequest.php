<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('view', $this->route('ticket'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
