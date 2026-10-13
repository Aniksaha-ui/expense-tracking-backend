<?php

namespace App\Http\Requests\Api\FinancialIntelligence;

class FinancialQuestionRequest extends FinancialIntelligenceRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['question' => ['required', 'string', 'max:1000']]);
    }
}
