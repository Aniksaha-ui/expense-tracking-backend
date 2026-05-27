<?php

namespace App\Http\Requests\Api\Transfers;

use App\Http\Requests\ApiFormRequest;

class TransferFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
