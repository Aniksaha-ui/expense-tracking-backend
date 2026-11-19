<?php

namespace App\Http\Requests\Api\Experiences;

use App\Http\Requests\ApiFormRequest;

class StoreExperienceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'job_title' => ['required', 'string', 'max:191'],
            'company' => ['required', 'string', 'max:191'],
            'location' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'start_date' => ['required', 'string', 'max:191'],
            'end_date' => ['required', 'string', 'max:191'],
            'is_current' => ['required', 'string', 'max:191'],
            'image_url' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'integer'],
        ];
    }
}
