<?php

namespace App\Http\Requests\Api\Trainings;

use App\Http\Requests\ApiFormRequest;

class UpdateTrainingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'provider' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'required', 'string', 'max:191'],
            'start_date' => ['sometimes', 'required', 'string', 'max:191'],
            'end_date' => ['sometimes', 'required', 'string', 'max:191'],
            'certificate_url' => ['sometimes', 'required', 'string', 'max:191'],
            'image' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp,svg,avif,heic,heif', 'max:5120'],
            'status' => ['sometimes', 'required', 'integer', 'in:0,1'],
        ];
    }
}
