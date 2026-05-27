<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\StoreAccountRequest;
use App\Http\Requests\Api\Accounts\UpdateAccountRequest;
use App\Http\Resources\Api\AccountResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            return $this->successResponse(
                AccountResource::collection($this->accountService->listAccounts(auth()->id()))
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->accountService->storeAccount($request->validated(), auth()->id());

            return $this->successResponse(
                new AccountResource($account),
                'Account created successfully.',
                201
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function show(int $accountId): JsonResponse
    {
        try {
            return $this->successResponse(
                new AccountResource($this->accountService->getAccount(auth()->id(), $accountId))
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function update(UpdateAccountRequest $request, int $accountId): JsonResponse
    {
        try {
            $account = $this->accountService->updateAccount($request->validated(), auth()->id(), $accountId);

            return $this->successResponse(
                new AccountResource($account),
                'Account updated successfully.'
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
