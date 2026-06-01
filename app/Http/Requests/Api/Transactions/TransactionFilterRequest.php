<?php

namespace App\Http\Requests\Api\Transactions;

use App\Enums\TransactionType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransactionFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(TransactionType::values())],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
