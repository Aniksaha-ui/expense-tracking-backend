<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Trainings\StoreTrainingRequest;
use App\Http\Requests\Api\Trainings\UpdateTrainingRequest;
use App\Http\Resources\Api\TrainingResource;
use App\Services\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TrainingController extends Controller
{
    public function __construct(private readonly TrainingService $trainingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
            $search = trim((string) $request->query('search', ''));

            return $this->successResponse(
                TrainingResource::collection($this->trainingService->listTrainings($perPage, $search ?: null))
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch trainings.', status: 500);
        }
    }

    public function store(StoreTrainingRequest $request): JsonResponse
    {
        try {
            $training = $this->trainingService->createTraining($request->validated());

            return $this->successResponse(new TrainingResource($training), 'Training created successfully.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create training.', status: 500);
        }
    }

    public function show(int $trainingId): JsonResponse
    {
        try {
            return $this->successResponse(new TrainingResource($this->trainingService->getTraining($trainingId)));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch training.', status: 500);
        }
    }

    public function update(UpdateTrainingRequest $request, int $trainingId): JsonResponse
    {
        try {
            $training = $this->trainingService->updateTraining($trainingId, $request->validated());

            return $this->successResponse(new TrainingResource($training), 'Training updated successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to update training.', status: 500);
        }
    }

    public function destroy(int $trainingId): JsonResponse
    {
        try {
            $this->trainingService->deleteTraining($trainingId);

            return $this->successResponse([], 'Training deleted successfully.');
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to delete training.', status: 500);
        }
    }
}
