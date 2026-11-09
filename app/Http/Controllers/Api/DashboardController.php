<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->dashboardService->summary((string) $request->attributes->get('auth.userId')));
    }

    public function upcoming(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->dashboardService->upcoming((string) $request->attributes->get('auth.userId')));
    }

    public function recentAnnouncements(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->dashboardService->recentAnnouncements((string) $request->attributes->get('auth.userId')));
    }
}
