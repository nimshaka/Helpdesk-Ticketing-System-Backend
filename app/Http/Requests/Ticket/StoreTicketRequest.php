<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreTicketRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['attachments.*' => 'attachment'];
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Customer) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'attachments' => ['sometimes', 'array', 'max:'.config('helpdesk.attachment_max_files')],
            'attachments.*' => [
                'file',
                File::types(config('helpdesk.attachment_types'))->max(config('helpdesk.attachment_max_kb')),
            ],
        ];
    }
}
