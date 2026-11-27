<?php

namespace App\Http\Requests\Api\Projects;

use App\Http\Requests\ApiFormRequest;

class ProjectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['project_name' => ['required', 'string', 'max:200'], 'description' => ['required', 'string'], 'website_link' => ['required', 'string', 'max:191'], 'frontend_tech' => ['required', 'string', 'max:191'], 'backend_tech' => ['required', 'string', 'max:191'], 'database' => ['required', 'string', 'max:191'], 'github_link' => ['required', 'string', 'max:191'], 'image' => ['nullable', 'string', 'max:191'], 'no_of_developers' => ['required', 'integer', 'min:1'], 'developers_name' => ['required', 'string', 'max:191'], 'start_date' => ['nullable', 'string', 'max:191'], 'end_date' => ['nullable', 'string', 'max:191'], 'isPublished' => ['required', 'boolean'], 'published_at' => ['nullable', 'string', 'max:191']];
    }
}
