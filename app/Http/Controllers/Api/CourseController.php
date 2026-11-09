<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CourseService;
use App\Support\ApiResponse;
use App\Support\ApiValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $input = ApiValidation::query($request, [
            'activeOnly' => ['sometimes', 'in:true,false'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $page = (int) ($input['page'] ?? 1);
        $limit = (int) ($input['limit'] ?? 20);
        $activeOnly = ($input['activeOnly'] ?? 'true') === 'true';
        $courses = $this->courseService->list((string) $request->attributes->get('auth.userId'), $activeOnly, $page, $limit);

        return ApiResponse::success($request, $courses, 200, ['pagination' => ['page' => $page, 'limit' => $limit]]);
    }

    public function show(Request $request, string $courseId): JsonResponse
    {
        if (! Str::isUuid($courseId)) {
            return ApiResponse::error($request, 'Invalid course ID', 400, ['courseId' => ['Must be a UUID.']]);
        }

        $course = $this->courseService->detail((string) $request->attributes->get('auth.userId'), $courseId);
        return $course === null
            ? ApiResponse::error($request, 'Course not found', 404)
            : ApiResponse::success($request, $course);
    }

    public function modules(Request $request, string $courseId): JsonResponse
    {
        if (! Str::isUuid($courseId)) {
            return ApiResponse::error($request, 'Invalid course ID', 400, ['courseId' => ['Must be a UUID.']]);
        }
        if (! $this->courseService->isEnrolled((string) $request->attributes->get('auth.userId'), $courseId)) {
            return ApiResponse::error($request, 'Course not found', 404);
        }

        return ApiResponse::success($request, $this->courseService->modules($courseId));
    }

    public function materials(Request $request, string $courseId): JsonResponse
    {
        if (! Str::isUuid($courseId)) {
            return ApiResponse::error($request, 'Invalid course ID', 400, ['courseId' => ['Must be a UUID.']]);
        }
        if (! $this->courseService->isEnrolled((string) $request->attributes->get('auth.userId'), $courseId)) {
            return ApiResponse::error($request, 'Course not found', 404);
        }

        return ApiResponse::success($request, $this->courseService->materials($courseId));
    }
}
