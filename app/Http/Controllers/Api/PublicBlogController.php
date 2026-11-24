<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BlogResource;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicBlogController extends Controller
{
    public function __construct(private readonly BlogService $blogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(
                BlogResource::collection($this->blogService->listPublishedBlogs($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch public blogs.', status: 500);
        }
    }

    public function show(int $blogId): JsonResponse
    {
        try {
            return $this->successResponse(new BlogResource($this->blogService->getPublishedBlog($blogId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch public blog.', status: 500);
        }
    }
}
