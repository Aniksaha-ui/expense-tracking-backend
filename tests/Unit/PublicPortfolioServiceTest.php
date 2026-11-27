<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PublicPortfolioController;
use App\Http\Requests\Api\ContactMessages\StorePublicContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\PortfolioProfileService;
use App\Services\PublicPortfolioService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PublicPortfolioServiceTest extends TestCase
{
    public function test_get_profile_formats_fields_and_decodes_json_collections(): void
    {
        $profileService = Mockery::mock(PortfolioProfileService::class);
        $profileService->shouldReceive('getOrCreate')->once()->andReturn((object) [
            'id' => 1,
            'name' => 'Anik Saha',
            'headline' => 'Software Engineer',
            'tagline' => 'Building reliable web products.',
            'bio' => 'Full-stack engineer.',
            'location' => 'Dhaka, Bangladesh',
            'email' => 'sahaanik1045@gmail.com',
            'phone' => null,
            'avatar' => 'https://cdn.example.com/avatar.png',
            'resume_url' => 'https://cdn.example.com/resume.pdf',
            'availability_status' => 'available',
            'availability_text' => 'Open to roles',
            'years_of_experience' => '4',
            'socials' => json_encode([['platform' => 'github', 'url' => 'https://github.com/anik']]),
            'skills' => json_encode([['group' => 'Frontend', 'items' => ['React', 'TypeScript']]]),
            'stats' => json_encode([['label' => 'Projects', 'value' => '20+']]),
            'seo_title' => 'Anik Saha',
            'seo_description' => 'Portfolio',
        ]);

        $service = new PublicPortfolioService($profileService);
        $profile = $service->getProfile();

        $this->assertSame('Anik Saha', $profile['name']);
        $this->assertSame(4, $profile['years_of_experience']);
        $this->assertSame('https://cdn.example.com/avatar.png', $profile['avatar_url']);
        $this->assertSame('https://cdn.example.com/resume.pdf', $profile['resume_url']);
        $this->assertCount(1, $profile['socials']);
        $this->assertCount(1, $profile['skills']);
        $this->assertCount(1, $profile['stats']);
    }

    public function test_get_project_rejects_unpublished_or_missing_project(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->with('id', 999)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('isPublished', 1)->once()->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn(null);

        DB::shouldReceive('table')->with('projects')->once()->andReturn($builder);

        $service = new PublicPortfolioService(Mockery::mock(PortfolioProfileService::class));

        $this->expectException(RuntimeException::class);
        $service->getProject(999);
    }

    public function test_get_project_builds_deduplicated_tech_stack(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->with('id', 7)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('isPublished', 1)->once()->andReturnSelf();
        $builder->shouldReceive('first')->once()->andReturn((object) [
            'id' => 7,
            'project_name' => 'Published Portfolio App',
            'description' => 'Published project',
            'website_link' => 'https://example.com',
            'github_link' => 'https://github.com/example/app',
            'frontend_tech' => 'React, TypeScript',
            'backend_tech' => 'Laravel, TypeScript',
            'database' => 'MySQL',
            'image' => 'https://cdn.example.com/project.png',
            'no_of_developers' => '2',
            'developers_name' => 'Anik Saha',
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'published_at' => '2026-02-05',
        ]);

        DB::shouldReceive('table')->with('projects')->once()->andReturn($builder);

        $service = new PublicPortfolioService(Mockery::mock(PortfolioProfileService::class));
        $project = $service->getProject(7);

        $this->assertSame('Published Portfolio App', $project['project_name']);
        $this->assertSame(['React', 'TypeScript', 'Laravel', 'MySQL'], $project['tech_stack']);
        $this->assertSame(2, $project['no_of_developers']);
    }

    public function test_store_contact_ignores_honeypot_submissions(): void
    {
        $portfolioService = Mockery::mock(PublicPortfolioService::class);
        $contactService = Mockery::mock(ContactMessageService::class);
        $contactService->shouldNotReceive('createMessage');

        $request = Mockery::mock(StorePublicContactMessageRequest::class);
        $request->shouldReceive('isSpam')->once()->andReturn(true);

        $controller = new PublicPortfolioController($portfolioService, $contactService);
        $response = $controller->storeContact($request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('success', $payload['isExecute']);
        $this->assertSame('Thank you! Your message has been sent.', $payload['msg']);
    }

    public function test_list_achievements_maps_public_safe_fields(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('when')->once()->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->with('achieved_on')->once()->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->with('id')->once()->andReturnSelf();
        $builder->shouldReceive('limit')->with(6)->once()->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(collect([
            (object) [
                'id' => '139',
                'user_id' => 1,
                'type' => 'workplace',
                'title' => 'Dashboard — Home',
                'description' => 'Operational metrics dashboard.',
                'achieved_on' => '2026-10-16',
                'organization' => 'SSLCOMMERZ',
                'reference_url' => '/',
            ],
        ]));

        DB::shouldReceive('table')->with('achievements')->once()->andReturn($builder);

        $service = new PublicPortfolioService(Mockery::mock(PortfolioProfileService::class));
        $items = $service->listAchievements(6);

        $this->assertCount(1, $items);
        $this->assertSame(139, $items[0]['id']);
        $this->assertSame('workplace', $items[0]['type']);
        $this->assertSame('Dashboard — Home', $items[0]['title']);
        $this->assertSame('SSLCOMMERZ', $items[0]['organization']);
        $this->assertArrayNotHasKey('user_id', $items[0]);
    }
}
