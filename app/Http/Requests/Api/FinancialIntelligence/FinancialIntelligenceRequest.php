<?php

namespace App\Http\Requests\Api\FinancialIntelligence;

use App\Http\Requests\ApiFormRequest;

class FinancialIntelligenceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'expense_change_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'income_change_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
        ];
    }
}
