<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = 'Fetch information successfully', int $status = 200): JsonResponse
    {
        return response()->json([
            'isExecute' => 'success',
            'data' => $data,
            'msg' => $message,
        ], $status);
    }

    public static function error(string $message, mixed $data = [], int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'isExecute' => 'failed',
            'data' => $data,
            'msg' => $message,
        ];

        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
