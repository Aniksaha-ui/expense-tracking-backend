<?php

namespace App\Http\Requests\Api\Services;

use App\Http\Requests\ApiFormRequest;

class UpdateServiceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'required', 'string', 'max:191'],
            'icon' => ['sometimes', 'required', 'string', 'max:191'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:191'],
            'price' => ['sometimes', 'required', 'string', 'max:191'],
            'status' => ['sometimes', 'required', 'integer', 'in:0,1'],
        ];
    }
}
