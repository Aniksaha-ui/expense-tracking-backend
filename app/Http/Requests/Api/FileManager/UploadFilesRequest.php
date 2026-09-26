<?php

namespace App\Http\Requests\Api\FileManager;

use App\Http\Requests\ApiFormRequest;

class UploadFilesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer'],
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['required', 'file', 'max:25600'],
        ];
    }
}
