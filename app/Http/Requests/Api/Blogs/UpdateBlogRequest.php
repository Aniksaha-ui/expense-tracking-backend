<?php

namespace App\Http\Requests\Api\Blogs;

use App\Http\Requests\ApiFormRequest;

class UpdateBlogRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'content' => ['sometimes', 'required', 'string'],
            'components_json' => ['sometimes', 'nullable', 'json'],
            'short_description' => ['sometimes', 'required', 'string'],
            'category' => ['sometimes', 'string', 'max:191'],
            'fa_icon' => ['sometimes', 'required', 'string', 'max:191'],
            'view_count' => ['sometimes', 'integer', 'min:0'],
            'author_name' => ['sometimes', 'required', 'string', 'max:191'],
            'published_date' => ['sometimes', 'nullable', 'string', 'max:191'],
            'isPublished' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
