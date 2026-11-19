<?php

namespace App\Http\Requests\Api\Experiences;

use App\Http\Requests\ApiFormRequest;

class UpdateExperienceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'job_title' => ['sometimes', 'required', 'string', 'max:191'],
            'company' => ['sometimes', 'required', 'string', 'max:191'],
            'location' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'required', 'string'],
            'start_date' => ['sometimes', 'required', 'string', 'max:191'],
            'end_date' => ['sometimes', 'required', 'string', 'max:191'],
            'is_current' => ['sometimes', 'required', 'string', 'max:191'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', 'required', 'integer'],
        ];
    }
}
