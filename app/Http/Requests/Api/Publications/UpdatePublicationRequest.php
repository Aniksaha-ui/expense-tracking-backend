<?php

namespace App\Http\Requests\Api\Publications;

use App\Http\Requests\ApiFormRequest;

class UpdatePublicationRequest extends StorePublicationRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'abstract' => ['sometimes', 'required', 'string', 'max:191'],
            'authors' => ['sometimes', 'required', 'string', 'max:191'],
            'publication_date' => ['sometimes', 'required', 'string', 'max:191'],
            'publisher' => ['sometimes', 'required', 'string', 'max:191'],
            'doi' => ['sometimes', 'required', 'string', 'max:191'],
            'url' => ['sometimes', 'required', 'url', 'max:191'],
            'pdf_url' => ['sometimes', 'required', 'url', 'max:191'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}
