<?php

namespace App\Http\Requests\Api\FinancialGoals;

use App\Http\Requests\ApiFormRequest;

class StoreFinancialGoalRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'current_saved_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
