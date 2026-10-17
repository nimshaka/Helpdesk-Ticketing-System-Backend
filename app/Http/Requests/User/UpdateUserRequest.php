<?php

namespace App\Http\Requests\User;

use App\Http\Requests\AdminRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AdminRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required_without:email|string|max:255',
            'email' => [
                'required_without:name',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
        ];
    }
}
