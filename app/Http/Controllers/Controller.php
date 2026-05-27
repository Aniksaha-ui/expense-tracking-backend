<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function successResponse(mixed $data = [], string $message = 'Fetch information successfully', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function errorResponse(string $message, array $errors = [], int $status = 400, mixed $data = []): JsonResponse
    {
        return ApiResponse::error($message, $data, $status, $errors);
    }
}
