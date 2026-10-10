<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\AdminRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends AdminRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
