<?php

namespace App\Http\Requests\Api\Accounts;

use App\Enums\AccountType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(AccountType::values())],
            'institution_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
