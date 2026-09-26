<?php

namespace App\Http\Requests\Api\FileManager;

use App\Http\Requests\ApiFormRequest;

class UploadFileVersionRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:25600']];
    }
}
