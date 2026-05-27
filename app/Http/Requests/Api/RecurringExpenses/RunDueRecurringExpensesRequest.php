<?php

namespace App\Http\Requests\Api\RecurringExpenses;

use App\Http\Requests\ApiFormRequest;

class RunDueRecurringExpensesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'through_date' => ['nullable', 'date'],
        ];
    }
}
