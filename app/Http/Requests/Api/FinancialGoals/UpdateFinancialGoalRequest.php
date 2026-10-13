<?php

namespace App\Http\Requests\Api\FinancialGoals;

class UpdateFinancialGoalRequest extends StoreFinancialGoalRequest
{
    public function rules(): array
    {
        return array_map(fn (array $rules): array => array_merge(['sometimes'], $rules), parent::rules());
    }
}
