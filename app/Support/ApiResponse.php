<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    public static function error(string $message, int $status, mixed $details = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $status,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'message' => $message,
        ], $status);
    }
}