<?php

namespace App\Http\Requests\Api\FileManager;

use App\Http\Requests\ApiFormRequest;

class CreateFolderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'parent_id' => ['nullable', 'integer']];
    }
}
