<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BlogService
{
    public function listBlogs(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return DB::table('blogs')
            ->when($search, function ($query, string $value) {
                $query->where(function ($searchQuery) use ($value) {
                    $searchQuery->where('title', 'like', "%{$value}%")
                        ->orWhere('author_name', 'like', "%{$value}%")
                        ->orWhere('category', 'like', "%{$value}%")
                        ->orWhere('short_description', 'like', "%{$value}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getBlog(int $blogId): object
    {
        return $this->findBlog($blogId);
    }

    public function createBlog(array $data): object
    {
        $blogId = DB::table('blogs')->insertGetId([
            'title' => $data['title'],
            'content' => $data['content'],
            'components_json' => $data['components_json'] ?? null,
            'short_description' => $data['short_description'],
            'category' => $data['category'] ?? 'react',
            'fa_icon' => $data['fa_icon'],
            'view_count' => $data['view_count'] ?? 0,
            'author_name' => $data['author_name'],
            'published_date' => $data['published_date'] ?? null,
            'isPublished' => $data['isPublished'] ?? true,
            'image' => $data['image'] ?? null,
        ]);

        return $this->findBlog($blogId);
    }

    public function updateBlog(int $blogId, array $data): object
    {
        $this->findBlog($blogId);

        if ($data !== []) {
            DB::table('blogs')->where('id', $blogId)->update($data);
        }

        return $this->findBlog($blogId);
    }

    public function deleteBlog(int $blogId): void
    {
        if (DB::table('blogs')->where('id', $blogId)->delete() === 0) {
            throw new RuntimeException('Blog not found.');
        }
    }

    private function findBlog(int $blogId): object
    {
        $blog = DB::table('blogs')->where('id', $blogId)->first();

        if (! $blog) {
            throw new RuntimeException('Blog not found.');
        }

        return $blog;
    }
}
