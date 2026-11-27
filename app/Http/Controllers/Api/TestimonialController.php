<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Testimonials\StoreTestimonialRequest;
use App\Http\Requests\Api\Testimonials\UpdateTestimonialRequest;
use App\Http\Resources\Api\TestimonialResource;
use App\Services\TestimonialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TestimonialController extends Controller
{
    public function __construct(private readonly TestimonialService $testimonialService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(
                TestimonialResource::collection($this->testimonialService->listTestimonials($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch testimonials.', status: 500);
        }
    }

    public function store(StoreTestimonialRequest $request): JsonResponse
    {
        try {
            $testimonial = $this->testimonialService->createTestimonial($request->validated());

            return $this->successResponse(new TestimonialResource($testimonial), 'Testimonial created successfully.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create testimonial.', status: 500);
        }
    }

    public function show(int $testimonialId): JsonResponse
    {
        try {
            return $this->successResponse(new TestimonialResource($this->testimonialService->getTestimonial($testimonialId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch testimonial.', status: 500);
        }
    }

    public function update(UpdateTestimonialRequest $request, int $testimonialId): JsonResponse
    {
        try {
            $testimonial = $this->testimonialService->updateTestimonial($testimonialId, $request->validated());

            return $this->successResponse(new TestimonialResource($testimonial), 'Testimonial updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to update testimonial.', status: 500);
        }
    }

    public function destroy(int $testimonialId): JsonResponse
    {
        try {
            $this->testimonialService->deleteTestimonial($testimonialId);

            return $this->successResponse([], 'Testimonial deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to delete testimonial.', status: 500);
        }
    }
}
