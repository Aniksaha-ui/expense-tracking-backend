<?php

namespace App\Services;

use App\Http\Resources\Api\BlogResource;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublicPortfolioService
{
    public function __construct(private readonly PortfolioProfileService $portfolioProfileService) {}

    public function getProfile(): array
    {
        $profile = $this->portfolioProfileService->getOrCreate();

        return [
            'name' => $profile->name,
            'headline' => $profile->headline,
            'tagline' => $profile->tagline,
            'bio' => $profile->bio,
            'location' => $profile->location,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'avatar_url' => PublicStorageUrl::resolve($profile->avatar),
            'resume_url' => PublicStorageUrl::resolve($profile->resume_url),
            'availability_status' => $profile->availability_status ?: 'available',
            'availability_text' => $profile->availability_text,
            'years_of_experience' => (int) $profile->years_of_experience,
            'socials' => PortfolioProfileService::decodeJson($profile->socials),
            'skills' => PortfolioProfileService::decodeJson($profile->skills),
            'stats' => PortfolioProfileService::decodeJson($profile->stats),
            'seo_title' => $profile->seo_title,
            'seo_description' => $profile->seo_description,
        ];
    }

    public function listExperiences(?int $limit = null): array
    {
        $query = DB::table('expriences')
            ->where('status', 1)
            ->orderByDesc('is_current')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'job_title' => $item->job_title,
            'company' => $item->company,
            'location' => $item->location,
            'description' => $item->description,
            'start_date' => $item->start_date,
            'end_date' => $item->end_date,
            'is_current' => (bool) $item->is_current,
            'image_url' => PublicStorageUrl::resolve($item->image_url),
        ])->all();
    }

    public function listProjects(?int $limit = null): array
    {
        $query = DB::table('projects')
            ->where('isPublished', 1)
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => $this->formatProject($item))->all();
    }

    public function getProject(int $projectId): array
    {
        $project = DB::table('projects')
            ->where('id', $projectId)
            ->where('isPublished', 1)
            ->first();

        if (! $project) {
            throw new RuntimeException('Project not found.');
        }

        return $this->formatProject($project);
    }

    public function listPublications(?int $limit = null): array
    {
        $query = DB::table('publications')
            ->where('status', 1)
            ->orderByDesc('publication_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'title' => $item->title,
            'abstract' => $item->abstract,
            'authors' => $item->authors,
            'publication_date' => $item->publication_date,
            'publisher' => $item->publisher,
            'doi' => $item->doi,
            'url' => $item->url,
            'pdf_url' => PublicStorageUrl::resolve($item->pdf_url),
        ])->all();
    }

    public function listTestimonials(?int $limit = null): array
    {
        $query = DB::table('testimonials')
            ->where('status', 1)
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'name' => $item->name,
            'designation' => $item->designation,
            'feedback' => $item->feedback,
            'rating' => (int) $item->rating,
            'image_url' => PublicStorageUrl::resolve($item->image_url),
        ])->all();
    }

    public function listTrainings(?int $limit = null): array
    {
        $query = DB::table('trainings')
            ->where('status', 1)
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'title' => $item->title,
            'provider' => $item->provider,
            'description' => $item->description,
            'start_date' => $item->start_date,
            'end_date' => $item->end_date,
            'certificate_url' => PublicStorageUrl::resolve($item->certificate_url),
            'image_url' => PublicStorageUrl::resolve($item->image_url),
        ])->all();
    }

    public function listServices(?int $limit = null): array
    {
        $query = DB::table('services')
            ->where('status', 1)
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'icon' => $item->icon,
            'image_url' => PublicStorageUrl::resolve($item->image_url),
            'price' => $item->price,
        ])->all();
    }

    public function listAchievements(?int $limit = null, ?string $type = null): array
    {
        $query = DB::table('achievements')
            ->when(
                $type && in_array($type, ['workplace', 'personal', 'other'], true),
                fn ($q) => $q->where('type', $type)
            )
            ->orderByDesc('achieved_on')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (object $item): array => [
            'id' => (int) $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'description' => $item->description,
            'achieved_on' => $item->achieved_on,
            'organization' => $item->organization,
            'reference_url' => $item->reference_url,
        ])->all();
    }

    public function getHome(): array
    {
        $latestBlogs = DB::table('blogs')
            ->where('isPublished', 1)
            ->orderByDesc('published_date')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        return [
            'profile' => $this->getProfile(),
            'experiences' => $this->listExperiences(),
            'projects' => $this->listProjects(6),
            'services' => $this->listServices(),
            'testimonials' => $this->listTestimonials(),
            'blogs' => BlogResource::collection($latestBlogs)->resolve(),
            'publications' => $this->listPublications(3),
            'trainings' => $this->listTrainings(4),
            'achievements' => $this->listAchievements(6),
            'counts' => [
                'projects' => DB::table('projects')->where('isPublished', 1)->count(),
                'publications' => DB::table('publications')->where('status', 1)->count(),
                'blogs' => DB::table('blogs')->where('isPublished', 1)->count(),
                'trainings' => DB::table('trainings')->where('status', 1)->count(),
                'experiences' => DB::table('expriences')->where('status', 1)->count(),
                'testimonials' => DB::table('testimonials')->where('status', 1)->count(),
                'achievements' => DB::table('achievements')->count(),
            ],
        ];
    }

    private function formatProject(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'project_name' => $item->project_name,
            'description' => $item->description,
            'website_link' => $item->website_link,
            'github_link' => $item->github_link,
            'frontend_tech' => $item->frontend_tech,
            'backend_tech' => $item->backend_tech,
            'database' => $item->database,
            'tech_stack' => $this->buildTechStack($item->frontend_tech, $item->backend_tech, $item->database),
            'image' => PublicStorageUrl::resolve($item->image),
            'no_of_developers' => (int) $item->no_of_developers,
            'developers_name' => $item->developers_name,
            'start_date' => $item->start_date,
            'end_date' => $item->end_date,
            'published_at' => $item->published_at,
        ];
    }

    private function buildTechStack(?string ...$fields): array
    {
        $items = [];

        foreach ($fields as $field) {
            if ($field === null || trim($field) === '') {
                continue;
            }

            foreach (preg_split('/[,;|]+/', $field) ?: [] as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        return array_values(array_unique($items));
    }
}
