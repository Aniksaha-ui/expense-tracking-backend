<?php

namespace App\Http\Requests\Api\Transfers;

use App\Http\Requests\ApiFormRequest;

class StoreWithdrawalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer', 'different:to_account_id'],
            'to_account_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'note' => ['nullable', 'string'],
            'transfer_date' => ['nullable', 'date'],
        ];
    }
}
