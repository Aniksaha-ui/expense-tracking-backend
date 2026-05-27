<?php

namespace App\Http\Requests\Api\RecurringExpenses;

use App\Http\Requests\ApiFormRequest;

class RunRecurringExpenseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'run_date' => ['nullable', 'date'],
        ];
    }
}
