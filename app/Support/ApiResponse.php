<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

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


    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message = 'OK',
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $resourceClass::collection($paginator->getCollection())->resolve(),
            'message' => $message,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
