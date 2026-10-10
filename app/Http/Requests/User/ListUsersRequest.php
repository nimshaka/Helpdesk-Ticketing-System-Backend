<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\AdminRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends AdminRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}