<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = 'Fetch information successfully', int $status = 200): JsonResponse
    {
        $traceId = TraceId::current();

        return response()->json([
            'isExecute' => 'success',
            'data' => $data,
            'msg' => $message,
            'trace_id' => $traceId,
        ], $status)->header(TraceId::HEADER, $traceId);
    }

    public static function error(string $message, mixed $data = [], int $status = 400, array $errors = []): JsonResponse
    {
        $traceId = TraceId::current();

        $response = [
            'isExecute' => 'failed',
            'data' => $data,
            'msg' => $message,
            'trace_id' => $traceId,
        ];

        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status)->header(TraceId::HEADER, $traceId);
    }
}
