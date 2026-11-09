<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GradeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GradeController extends Controller
{
    public function __construct(private readonly GradeService $gradeService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->gradeService->summary((string) $request->attributes->get('auth.userId')));
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->gradeService->releasedGrades((string) $request->attributes->get('auth.userId')));
    }

    public function course(Request $request, string $courseId): JsonResponse
    {
        if (! Str::isUuid($courseId)) {
            return ApiResponse::error($request, 'Invalid course ID', 400, ['courseId' => ['Must be a UUID.']]);
        }
        $studentId = (string) $request->attributes->get('auth.userId');
        if (! $this->gradeService->isEnrolledInCourse($studentId, $courseId)) {
            return ApiResponse::error($request, 'Course not found', 404);
        }

        return ApiResponse::success($request, $this->gradeService->releasedGrades($studentId, $courseId));
    }
}
