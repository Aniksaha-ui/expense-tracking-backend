<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'components_json' => is_string($this->components_json)
                ? json_decode($this->components_json, true)
                : $this->components_json,
            'short_description' => $this->short_description,
            'category' => $this->category,
            'fa_icon' => $this->fa_icon,
            'view_count' => (int) $this->view_count,
            'author_name' => $this->author_name,
            'published_date' => $this->published_date,
            'isPublished' => (bool) $this->isPublished,
            'image' => $this->image,
        ];
    }
}
