<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    /** @param array<string, mixed>|null $extra */
    public static function success(Request $request, mixed $data, int $status = 200, ?array $extra = null): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'data' => $data,
        ], $extra ?? []), $status)->header('X-Request-ID', (string) $request->attributes->get('requestId'));
    }

    /** @param array<string, mixed>|null $errors */
    public static function error(Request $request, string $message, int $status, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'requestId' => $request->attributes->get('requestId'),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status)
            ->header('X-Request-ID', (string) $request->attributes->get('requestId'));
    }
}
