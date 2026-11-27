<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Projects\ProjectRequest;
use App\Http\Resources\Api\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return $this->successResponse(ProjectResource::collection($this->projects->list(min(max($request->integer('per_page', 15), 1), 100), $request->filled('search') ? trim($request->string('search')) : null)));
        } catch (\Throwable $e) {
            return $this->errorResponse('Unable to fetch projects.', status: 500);
        }
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(new ProjectResource($this->projects->create($request->validated())), 'Project created successfully.', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Unable to create project.', status: 500);
        }
    }

    public function show(int $projectId): JsonResponse
    {
        try {
            return $this->successResponse(new ProjectResource($this->projects->get($projectId)));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), status: 404);
        }
    }

    public function update(ProjectRequest $request, int $projectId): JsonResponse
    {
        try {
            return $this->successResponse(new ProjectResource($this->projects->update($projectId, $request->validated())), 'Project updated successfully.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), status: 404);
        }
    }

    public function destroy(int $projectId): JsonResponse
    {
        try {
            $this->projects->delete($projectId);

            return $this->successResponse([], 'Project deleted successfully.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), status: 404);
        }
    }
}
