<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ProfileUpdateRequest;
use App\Services\StudentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    public function __construct(private readonly StudentService $studentService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $profile = $this->studentService->getProfile((string) $request->attributes->get('auth.userId'));
        return $profile === null
            ? ApiResponse::error($request, 'Student profile not found', 404)
            : ApiResponse::success($request, $profile);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $profile = $this->studentService->updateProfile(
            (string) $request->attributes->get('auth.userId'),
            $request->validated(),
        );

        return $profile === null
            ? ApiResponse::error($request, 'Student profile not found', 404)
            : ApiResponse::success($request, $profile);
    }
}
