<?php

namespace App\Http\Requests\Api\Categories;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['EXPENSE', 'INCOME'])],
        ];
    }
}
