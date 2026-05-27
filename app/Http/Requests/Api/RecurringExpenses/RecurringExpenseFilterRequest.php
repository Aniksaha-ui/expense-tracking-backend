<?php

namespace App\Http\Requests\Api\RecurringExpenses;

use App\Http\Requests\ApiFormRequest;

class RecurringExpenseFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
