<?php

namespace App\Http\Requests\Api\Transactions;

use App\Http\Requests\ApiFormRequest;

class ScanTransactionReceiptRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
