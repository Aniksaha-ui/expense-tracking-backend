<?php

namespace App\Http\Requests\Api\Categories;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['EXPENSE', 'INCOME'])],
        ];
    }
}
