<?php

namespace App\Http\Requests\Api\Testimonials;

use App\Http\Requests\ApiFormRequest;

class UpdateTestimonialRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'designation' => ['sometimes', 'required', 'string', 'max:191'],
            'feedback' => ['sometimes', 'required', 'string', 'max:191'],
            'rating' => ['sometimes', 'required', 'string', 'max:191'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', 'required', 'integer', 'in:0,1'],
        ];
    }
}
