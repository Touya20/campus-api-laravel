<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ApiValidation
{
    /** @param array<string, mixed> $data @param array<string, mixed> $rules @param list<string> $allowedFields @return array<string, mixed>|JsonResponse */
    public static function validate(Request $request, array $data, array $rules, array $allowedFields, string $message = 'Invalid request'): array|JsonResponse
    {
        $validator = Validator::make($data, $rules);
        $passed = $validator->passes();
        $unknownFields = array_diff(array_keys($data), $allowedFields);
        foreach ($unknownFields as $field) {
            $validator->errors()->add((string) $field, 'This field is not allowed.');
        }

        if (! $passed || $unknownFields !== []) {
            return ApiResponse::error($request, $message, 400, $validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /** @param array<string, mixed> $rules @return array<string, mixed>|JsonResponse */
    public static function query(Request $request, array $rules, string $message = 'Invalid request'): array|JsonResponse
    {
        return self::validate($request, $request->query(), $rules, array_keys($rules), $message);
    }
}
