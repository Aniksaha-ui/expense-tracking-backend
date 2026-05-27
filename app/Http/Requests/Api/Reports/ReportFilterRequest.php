<?php

namespace App\Http\Requests\Api\Reports;

use App\Http\Requests\ApiFormRequest;

class ReportFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'through_date' => ['nullable', 'date'],
        ];
    }
}
