<?php

namespace App\Http\Requests\Api\Blogs;

use App\Http\Requests\ApiFormRequest;

class StoreBlogRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'content' => ['required', 'string'],
            'components_json' => ['nullable', 'json'],
            'short_description' => ['required', 'string'],
            'category' => ['sometimes', 'string', 'max:191'],
            'fa_icon' => ['required', 'string', 'max:191'],
            'view_count' => ['sometimes', 'integer', 'min:0'],
            'author_name' => ['required', 'string', 'max:191'],
            'published_date' => ['nullable', 'string', 'max:191'],
            'isPublished' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
