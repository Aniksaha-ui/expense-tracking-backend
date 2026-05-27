<?php

namespace App\Http\Requests\Api\RecurringExpenses;

use App\Enums\RecurringFrequency;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringExpenseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'frequency' => ['required', Rule::in(RecurringFrequency::values())],
            'start_date' => ['required', 'date'],
            'next_run_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
