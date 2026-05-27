<?php

namespace App\Http\Requests\Api\RecurringExpenses;

use App\Enums\RecurringFrequency;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringExpenseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'regex:/^\d+(\.\d{1,2})?$/'],
            'frequency' => ['sometimes', Rule::in(RecurringFrequency::values())],
            'start_date' => ['sometimes', 'date'],
            'next_run_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
