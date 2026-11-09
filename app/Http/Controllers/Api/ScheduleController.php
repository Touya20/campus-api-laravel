<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ScheduleService;
use App\Support\ApiResponse;
use App\Support\ApiValidation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respond($request, false);
    }

    public function week(Request $request): JsonResponse
    {
        return $this->respond($request, true);
    }

    private function respond(Request $request, bool $weeklyView): JsonResponse
    {
        $input = ApiValidation::query($request, [
            'semesterId' => ['sometimes', 'uuid'],
            'weekOf' => ['sometimes', 'date_format:Y-m-d'],
        ], 'Invalid schedule filter');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $studentId = (string) $request->attributes->get('auth.userId');
        $semesterId = $input['semesterId'] ?? null;
        if ($semesterId !== null && ! $this->scheduleService->isStudentEnrolledInSemester($studentId, $semesterId)) {
            return ApiResponse::error($request, 'Semester not found', 404);
        }

        $sessions = $this->scheduleService->sessions($studentId, $semesterId);
        $weekOf = $input['weekOf'] ?? ($weeklyView
            ? CarbonImmutable::now((string) config('services.campus.timezone', 'Asia/Manila'))->toDateString()
            : null);
        if ($weekOf === null) {
            return ApiResponse::success($request, $sessions, 200, [
                'meta' => [
                    'timeZone' => config('services.campus.timezone', 'Asia/Manila'),
                    'weekStartsOn' => 'Monday',
                    'semesterId' => $semesterId,
                ],
            ]);
        }

        $week = $this->scheduleService->attachDates($sessions, $weekOf);
        return ApiResponse::success($request, $week['sessions'], 200, [
            'meta' => [
                'timeZone' => config('services.campus.timezone', 'Asia/Manila'),
                'weekStartsOn' => 'Monday',
                'weekOf' => $weekOf,
                'weekStart' => $week['weekStart'],
                'weekEnd' => $week['weekEnd'],
                'semesterId' => $semesterId,
            ],
        ]);
    }
}
