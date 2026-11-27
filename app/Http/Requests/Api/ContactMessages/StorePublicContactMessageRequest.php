<?php

namespace App\Http\Requests\Api\ContactMessages;

use App\Http\Requests\ApiFormRequest;

class StorePublicContactMessageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'subject' => ['nullable', 'string', 'max:191'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot: real visitors never see/fill this field.
            'website' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function isSpam(): bool
    {
        return trim((string) $this->input('website', '')) !== '';
    }
}
