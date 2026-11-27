<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Publications\StorePublicationRequest;
use App\Http\Requests\Api\Publications\UpdatePublicationRequest;
use App\Http\Resources\Api\PublicationResource;
use App\Services\PublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicationController extends Controller
{
    public function __construct(private readonly PublicationService $publications) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(PublicationResource::collection($this->publications->list(min(max($request->integer('per_page', 15), 1), 100), $search ?: null)));
        } catch (\Throwable) {
            return $this->errorResponse('Unable to fetch publications.', status: 500);
        }
    }

    public function store(StorePublicationRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(new PublicationResource($this->publications->create($request->validated())), 'Publication created successfully.', 201);
        } catch (\Throwable) {
            return $this->errorResponse('Unable to create publication.', status: 500);
        }
    }

    public function show(int $publicationId): JsonResponse
    {
        try {
            return $this->successResponse(new PublicationResource($this->publications->get($publicationId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function update(UpdatePublicationRequest $request, int $publicationId): JsonResponse
    {
        try {
            return $this->successResponse(new PublicationResource($this->publications->update($publicationId, $request->validated())), 'Publication updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable) {
            return $this->errorResponse('Unable to update publication.', status: 500);
        }
    }

    public function destroy(int $publicationId): JsonResponse
    {
        try {
            $this->publications->delete($publicationId);

            return $this->successResponse([], 'Publication deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable) {
            return $this->errorResponse('Unable to delete publication.', status: 500);
        }
    }
}
