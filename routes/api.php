<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:auth')->prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware(['auth.jwt', 'role:student'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/chat', [ChatController::class, 'reply'])->middleware('throttle:chat');

        Route::get('/students/me', [StudentController::class, 'show']);
        Route::patch('/students/me', [StudentController::class, 'update'])->middleware('throttle:sensitive');

        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::get('/dashboard/upcoming', [DashboardController::class, 'upcoming']);
        Route::get('/dashboard/recent-announcements', [DashboardController::class, 'recentAnnouncements']);

        Route::get('/courses', [CourseController::class, 'index']);
        Route::get('/courses/{courseId}/modules', [CourseController::class, 'modules']);
        Route::get('/courses/{courseId}/materials', [CourseController::class, 'materials']);
        Route::get('/courses/{courseId}', [CourseController::class, 'show']);

        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::get('/assignments/upcoming', [AssignmentController::class, 'upcoming']);
        Route::get('/assignments/{assignmentId}/drafts', [AssignmentController::class, 'showDraft']);
        Route::post('/assignments/{assignmentId}/drafts', [AssignmentController::class, 'saveDraft'])->middleware('throttle:sensitive');
        Route::post('/assignments/{assignmentId}/submissions', [AssignmentController::class, 'submit'])->middleware('throttle:sensitive');
        Route::get('/assignments/{assignmentId}/submissions/me', [AssignmentController::class, 'showSubmission']);
        Route::get('/assignments/{assignmentId}', [AssignmentController::class, 'show']);
        Route::patch('/drafts/{draftId}', [AssignmentController::class, 'updateDraft'])->middleware('throttle:sensitive');
        Route::delete('/drafts/{draftId}', [AssignmentController::class, 'deleteDraft'])->middleware('throttle:sensitive');

        Route::get('/schedule', [ScheduleController::class, 'index']);
        Route::get('/schedule/week', [ScheduleController::class, 'week']);

        Route::get('/grades/summary', [GradeController::class, 'summary']);
        Route::get('/grades', [GradeController::class, 'index']);
        Route::get('/grades/{courseId}', [GradeController::class, 'course']);

        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcementId}', [AnnouncementController::class, 'show']);
        Route::post('/announcements/{announcementId}/read', [AnnouncementController::class, 'markRead'])->middleware('throttle:sensitive');
    });
});

