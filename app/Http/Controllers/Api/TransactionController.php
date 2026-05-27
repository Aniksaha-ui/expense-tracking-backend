<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Transactions\StoreDepositRequest;
use App\Http\Requests\Api\Transactions\StoreExpenseRequest;
use App\Http\Requests\Api\Transactions\StoreIncomeRequest;
use App\Http\Requests\Api\Transactions\TransactionFilterRequest;
use App\Http\Resources\Api\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    public function index(TransactionFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                TransactionResource::collection(
                    $this->transactionService->listTransactions(auth()->id(), $request->validated())
                )
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function storeIncome(StoreIncomeRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createIncome($request->validated(), auth()->id());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Income transaction created successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function storeExpense(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createExpense($request->validated(), auth()->id());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Expense transaction created successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function storeDeposit(StoreDepositRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createDeposit($request->validated(), auth()->id());

            return $this->successResponse(
                new TransactionResource($transaction),
                'Deposit transaction created successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
