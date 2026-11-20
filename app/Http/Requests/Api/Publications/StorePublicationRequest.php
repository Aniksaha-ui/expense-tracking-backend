<?php

namespace App\Http\Requests\Api\Publications;

use App\Http\Requests\ApiFormRequest;

class StorePublicationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'abstract' => ['required', 'string', 'max:191'],
            'authors' => ['required', 'string', 'max:191'],
            'publication_date' => ['required', 'string', 'max:191'],
            'publisher' => ['required', 'string', 'max:191'],
            'doi' => ['required', 'string', 'max:191'],
            'url' => ['required', 'url', 'max:191'],
            'pdf_url' => ['required', 'url', 'max:191'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}
