<?php

namespace App\Http\Requests\Api\Accounts;

use App\Enums\AccountType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(AccountType::values())],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'opening_balance_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
