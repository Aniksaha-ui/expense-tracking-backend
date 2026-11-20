<?php

namespace App\Http\Requests\Api\Services;

use App\Http\Requests\ApiFormRequest;

class StoreServiceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:191'],
            'icon' => ['required', 'string', 'max:191'],
            'image_url' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'string', 'max:191'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
