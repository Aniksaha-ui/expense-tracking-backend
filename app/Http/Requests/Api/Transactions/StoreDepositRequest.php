<?php

namespace App\Http\Requests\Api\Transactions;

use App\Http\Requests\ApiFormRequest;

class StoreDepositRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'note' => ['nullable', 'string'],
            'transaction_date' => ['nullable', 'date'],
        ];
    }
}
