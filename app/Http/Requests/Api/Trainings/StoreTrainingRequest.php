<?php

namespace App\Http\Requests\Api\Trainings;

use App\Http\Requests\ApiFormRequest;

class StoreTrainingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'provider' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'string', 'max:191'],
            'end_date' => ['required', 'string', 'max:191'],
            'certificate_url' => ['required', 'string', 'max:191'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp,svg,avif,heic,heif', 'max:5120'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
