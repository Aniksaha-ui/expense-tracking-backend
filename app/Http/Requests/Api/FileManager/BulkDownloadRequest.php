<?php

namespace App\Http\Requests\Api\FileManager;

use App\Http\Requests\ApiFormRequest;

class BulkDownloadRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['file_ids' => ['required', 'array', 'min:1', 'max:100'], 'file_ids.*' => ['integer']];
    }
}
