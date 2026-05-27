<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\Auth\AuthTokenResource;
use App\Http\Resources\Api\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->register($request->validated());

            return $this->successResponse(
                new AuthTokenResource($data),
                'Registration completed successfully.',
                201
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login($request->validated());

            return $this->successResponse(
                new AuthTokenResource($data),
                'Login completed successfully.'
            );
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 401);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                return $this->errorResponse('Authorization token is required.', status: 401);
            }

            $this->authService->logout($request->user(), $token);

            return $this->successResponse([], 'Logout completed successfully.');
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            return $this->successResponse(
                new UserResource($request->user()),
                'Fetch information successfully'
            );
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 500);
        }
    }
}
