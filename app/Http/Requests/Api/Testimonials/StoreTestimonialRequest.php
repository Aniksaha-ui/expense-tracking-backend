<?php

namespace App\Http\Requests\Api\Testimonials;

use App\Http\Requests\ApiFormRequest;

class StoreTestimonialRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'designation' => ['required', 'string', 'max:191'],
            'feedback' => ['required', 'string', 'max:191'],
            'rating' => ['required', 'string', 'max:191'],
            'image_url' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
