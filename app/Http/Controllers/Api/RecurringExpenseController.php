<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecurringExpenses\RecurringExpenseFilterRequest;
use App\Http\Requests\Api\RecurringExpenses\RunDueRecurringExpensesRequest;
use App\Http\Requests\Api\RecurringExpenses\RunRecurringExpenseRequest;
use App\Http\Requests\Api\RecurringExpenses\StoreRecurringExpenseRequest;
use App\Http\Requests\Api\RecurringExpenses\UpdateRecurringExpenseRequest;
use App\Http\Resources\Api\RecurringExpenseResource;
use App\Http\Resources\Api\TransactionResource;
use App\Services\RecurringExpenseService;
use Illuminate\Http\JsonResponse;

class RecurringExpenseController extends Controller
{
    public function __construct(
        private readonly RecurringExpenseService $recurringExpenseService,
    ) {
    }

    public function index(RecurringExpenseFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                RecurringExpenseResource::collection(
                    $this->recurringExpenseService->listRecurringExpenses(auth()->id(), $request->validated())
                )
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function store(StoreRecurringExpenseRequest $request): JsonResponse
    {
        try {
            $recurringExpense = $this->recurringExpenseService->storeRecurringExpense($request->validated(), auth()->id());

            return $this->successResponse(
                new RecurringExpenseResource($recurringExpense),
                'Recurring expense created successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function update(UpdateRecurringExpenseRequest $request, int $recurringExpenseId): JsonResponse
    {
        try {
            $recurringExpense = $this->recurringExpenseService->updateRecurringExpense($request->validated(), auth()->id(), $recurringExpenseId);

            return $this->successResponse(
                new RecurringExpenseResource($recurringExpense),
                'Recurring expense updated successfully.'
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function run(RunRecurringExpenseRequest $request, int $recurringExpenseId): JsonResponse
    {
        try {
            $result = $this->recurringExpenseService->runRecurringExpense(
                auth()->id(),
                $recurringExpenseId,
                $request->validated()['run_date'] ?? null
            );

            return $this->successResponse([
                'recurring_expense' => (new RecurringExpenseResource($result['recurring_expense']))->resolve(),
                'transaction' => (new TransactionResource($result['transaction']))->resolve(),
            ], 'Recurring expense executed successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function runDue(RunDueRecurringExpensesRequest $request): JsonResponse
    {
        try {
            $result = $this->recurringExpenseService->runDueRecurringExpenses(
                auth()->id(),
                $request->validated()['through_date'] ?? null
            );

            $items = array_map(function (array $item) {
                return [
                    'recurring_expense' => (new RecurringExpenseResource($item['recurring_expense']))->resolve(),
                    'transaction' => (new TransactionResource($item['transaction']))->resolve(),
                ];
            }, $result['items']);

            return $this->successResponse([
                'count' => $result['count'],
                'items' => $items,
            ], 'Due recurring expenses executed successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
