<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use App\Support\ApiResponse;
use App\Support\ApiValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcementService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $input = ApiValidation::query($request, [
            'courseId' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], 'Invalid announcement request');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $studentId = (string) $request->attributes->get('auth.userId');
        $courseId = $input['courseId'] ?? null;
        if ($courseId !== null && ! $this->announcementService->isEnrolledInActiveCourse($studentId, $courseId)) {
            return ApiResponse::error($request, 'Course not found', 404);
        }

        $page = (int) ($input['page'] ?? 1);
        $limit = (int) ($input['limit'] ?? 20);
        $result = $this->announcementService->list($studentId, $courseId, $limit, ($page - 1) * $limit);

        return ApiResponse::success($request, $result['items'], 200, [
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $result['total']],
        ]);
    }

    public function show(Request $request, string $announcementId): JsonResponse
    {
        if (! Str::isUuid($announcementId)) {
            return ApiResponse::error($request, 'Invalid announcement ID', 400, ['announcementId' => ['Must be a UUID.']]);
        }
        $announcement = $this->announcementService->detail((string) $request->attributes->get('auth.userId'), $announcementId);
        return $announcement === null
            ? ApiResponse::error($request, 'Announcement not found', 404)
            : ApiResponse::success($request, $announcement);
    }

    public function markRead(Request $request, string $announcementId): JsonResponse
    {
        if (! Str::isUuid($announcementId)) {
            return ApiResponse::error($request, 'Invalid announcement ID', 400, ['announcementId' => ['Must be a UUID.']]);
        }
        $read = $this->announcementService->markRead((string) $request->attributes->get('auth.userId'), $announcementId);
        return $read === null
            ? ApiResponse::error($request, 'Announcement not found', 404)
            : ApiResponse::success($request, $read);
    }
}
