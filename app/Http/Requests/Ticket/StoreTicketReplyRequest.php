<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('reply', $this->route('ticket'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:5000',
        ];
    }
}
