<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Experiences\StoreExperienceRequest;
use App\Http\Requests\Api\Experiences\UpdateExperienceRequest;
use App\Http\Resources\Api\ExperienceResource;
use App\Services\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExperienceController extends Controller
{
    public function __construct(private readonly ExperienceService $experienceService) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(
                ExperienceResource::collection($this->experienceService->listExperiences($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch experiences.', status: 500);
        }
    }

    public function store(StoreExperienceRequest $request): JsonResponse
    {
        try {
            $experience = $this->experienceService->createExperience($request->validated());

            return $this->successResponse(new ExperienceResource($experience), 'Experience created successfully.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create experience.', status: 500);
        }
    }

    public function show(int $experienceId): JsonResponse
    {
        try {
            return $this->successResponse(new ExperienceResource($this->experienceService->getExperience($experienceId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch experience.', status: 500);
        }
    }

    public function update(UpdateExperienceRequest $request, int $experienceId): JsonResponse
    {
        try {
            $experience = $this->experienceService->updateExperience($experienceId, $request->validated());

            return $this->successResponse(new ExperienceResource($experience), 'Experience updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to update experience.', status: 500);
        }
    }

    public function destroy(int $experienceId): JsonResponse
    {
        try {
            $this->experienceService->deleteExperience($experienceId);

            return $this->successResponse([], 'Experience deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to delete experience.', status: 500);
        }
    }
}
