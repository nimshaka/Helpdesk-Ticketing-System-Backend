<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class ClaimTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('claim', $this->route('ticket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
