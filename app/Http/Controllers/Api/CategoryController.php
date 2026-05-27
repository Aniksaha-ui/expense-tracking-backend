<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Categories\StoreCategoryRequest;
use App\Http\Requests\Api\Categories\UpdateCategoryRequest;
use App\Http\Resources\Api\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            return $this->successResponse(
                CategoryResource::collection($this->categoryService->listCategories(auth()->id()))
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->storeCategory($request->validated(), auth()->id());

            return $this->successResponse(
                new CategoryResource($category),
                'Category created successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function update(UpdateCategoryRequest $request, int $categoryId): JsonResponse
    {
        try {
            $category = $this->categoryService->updateCategory($request->validated(), auth()->id(), $categoryId);

            return $this->successResponse(
                new CategoryResource($category),
                'Category updated successfully.'
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
