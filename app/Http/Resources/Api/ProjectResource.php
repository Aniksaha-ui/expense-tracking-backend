<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'project_name' => $this->project_name, 'description' => $this->description, 'website_link' => $this->website_link, 'frontend_tech' => $this->frontend_tech, 'backend_tech' => $this->backend_tech, 'database' => $this->database, 'github_link' => $this->github_link, 'image' => $this->image, 'no_of_developers' => (int) $this->no_of_developers, 'developers_name' => $this->developers_name, 'start_date' => $this->start_date, 'end_date' => $this->end_date, 'isPublished' => (bool) $this->isPublished, 'published_at' => $this->published_at];
    }
}
