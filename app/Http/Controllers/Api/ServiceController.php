<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Services\StoreServiceRequest;
use App\Http\Requests\Api\Services\UpdateServiceRequest;
use App\Http\Resources\Api\ServiceResource;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(
                ServiceResource::collection($this->serviceService->listServices($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch services.', status: 500);
        }
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $service = $this->serviceService->createService($request->validated());

            return $this->successResponse(new ServiceResource($service), 'Service created successfully.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create service.', status: 500);
        }
    }

    public function show(int $serviceId): JsonResponse
    {
        try {
            return $this->successResponse(new ServiceResource($this->serviceService->getService($serviceId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch service.', status: 500);
        }
    }

    public function update(UpdateServiceRequest $request, int $serviceId): JsonResponse
    {
        try {
            $service = $this->serviceService->updateService($serviceId, $request->validated());

            return $this->successResponse(new ServiceResource($service), 'Service updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to update service.', status: 500);
        }
    }

    public function destroy(int $serviceId): JsonResponse
    {
        try {
            $this->serviceService->deleteService($serviceId);

            return $this->successResponse([], 'Service deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to delete service.', status: 500);
        }
    }
}
