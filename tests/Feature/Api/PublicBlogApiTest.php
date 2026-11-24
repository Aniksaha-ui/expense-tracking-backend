<?php

namespace Tests\Feature\Api;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class PublicBlogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.portfolio.blog_api_token' => 'portfolio-test-token']);
    }

    public function test_public_blog_api_requires_the_static_token_and_hides_unpublished_blogs(): void
    {
        $publishedBlog = Blog::query()->create($this->blogAttributes(['isPublished' => true]));
        $unpublishedBlog = Blog::query()->create($this->blogAttributes([
            'title' => 'Draft post',
            'isPublished' => false,
        ]));

        $this->getJson('/api/public/blogs')
            ->assertUnauthorized()
            ->assertJsonPath('msg', 'Invalid portfolio API token.');

        $this->withHeader('X-Portfolio-Token', 'portfolio-test-token')
            ->getJson('/api/public/blogs')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $publishedBlog->id);

        $this->withHeader('X-Portfolio-Token', 'portfolio-test-token')
            ->getJson('/api/public/blogs/'.$unpublishedBlog->id)
            ->assertNotFound();
    }

    private function blogAttributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Published post',
            'content' => '<p>Post content</p>',
            'short_description' => 'A post summary',
            'category' => 'Laravel',
            'fa_icon' => 'fa-code',
            'view_count' => 0,
            'author_name' => 'Anik Saha',
            'published_date' => '2026-11-21',
            'isPublished' => true,
        ], $overrides);
    }
}
