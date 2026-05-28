<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Transfers\StoreTransferRequest;
use App\Http\Requests\Api\Transfers\StoreWithdrawalRequest;
use App\Http\Requests\Api\Transfers\TransferFilterRequest;
use App\Http\Requests\Api\Transfers\UpdateTransferRequest;
use App\Http\Resources\Api\TransferResource;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {
    }

    public function index(TransferFilterRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                TransferResource::collection(
                    $this->transferService->listTransfers(auth()->id(), $request->validated())
                )
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->transferService->createTransfer($request->validated(), auth()->id());

            return $this->successResponse(
                new TransferResource($transfer),
                'Transfer completed successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function withdrawToCash(StoreWithdrawalRequest $request): JsonResponse
    {
        try {
            $transfer = $this->transferService->createWithdrawalToCash($request->validated(), auth()->id());

            return $this->successResponse(
                new TransferResource($transfer),
                'Withdrawal completed successfully.',
                201
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function update(UpdateTransferRequest $request, int $transferId): JsonResponse
    {
        try {
            $transfer = $this->transferService->updateTransfer($transferId, $request->validated(), auth()->id());

            return $this->successResponse(
                new TransferResource($transfer),
                'Transfer updated successfully.'
            );
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === 'Invalid transfer id.' ? 404 : 422;

            return $this->errorResponse($exception->getMessage(), status: $status);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
