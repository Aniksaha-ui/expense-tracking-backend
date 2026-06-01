<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reports\ReportFilterRequest;
use App\Http\Resources\Api\AccountResource;
use App\Http\Resources\Api\RecurringExpenseResource;
use App\Http\Resources\Api\CategoryResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    public function summary(ReportFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->reportService->summary(auth()->id(), $request->validated()));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function accountBalances(): JsonResponse
    {
        try {
            return $this->successResponse(
                AccountResource::collection($this->reportService->accountBalances(auth()->id()))
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function categoryBreakdown(ReportFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->reportService->categoryBreakdown(auth()->id(), $request->validated()));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function daywiseExpenses(ReportFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->reportService->daywiseExpenses(auth()->id(), $request->validated()));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function cashFlow(ReportFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->reportService->cashFlow(auth()->id(), $request->validated()));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function dueRecurring(ReportFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                RecurringExpenseResource::collection(
                    $this->reportService->dueRecurringExpenses(auth()->id(), $request->validated()['through_date'] ?? null)
                )
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
