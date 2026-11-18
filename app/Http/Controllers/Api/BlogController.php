<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Blogs\StoreBlogRequest;
use App\Http\Requests\Api\Blogs\UpdateBlogRequest;
use App\Http\Resources\Api\BlogResource;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BlogController extends Controller
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
                BlogResource::collection($this->blogService->listBlogs($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch blogs.', status: 500);
        }
    }

    public function store(StoreBlogRequest $request): JsonResponse
    {
        try {
            $blog = $this->blogService->createBlog($request->validated());

            return $this->successResponse(new BlogResource($blog), 'Blog created successfully.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create blog.', status: 500);
        }
    }

    public function show(int $blogId): JsonResponse
    {
        try {
            return $this->successResponse(new BlogResource($this->blogService->getBlog($blogId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch blog.', status: 500);
        }
    }

    public function update(UpdateBlogRequest $request, int $blogId): JsonResponse
    {
        try {
            $blog = $this->blogService->updateBlog($blogId, $request->validated());

            return $this->successResponse(new BlogResource($blog), 'Blog updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to update blog.', status: 500);
        }
    }

    public function destroy(int $blogId): JsonResponse
    {
        try {
            $this->blogService->deleteBlog($blogId);

            return $this->successResponse([], 'Blog deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to delete blog.', status: 500);
        }
    }
}
