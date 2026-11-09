<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssignmentService;
use App\Support\ApiResponse;
use App\Support\ApiValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->assignmentService->list((string) $request->attributes->get('auth.userId')));
    }

    public function upcoming(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->assignmentService->upcoming((string) $request->attributes->get('auth.userId')));
    }

    public function show(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return ApiResponse::error($request, 'Invalid assignment ID', 400, ['assignmentId' => ['Must be a UUID.']]);
        }
        $assignment = $this->assignmentService->detail((string) $request->attributes->get('auth.userId'), $assignmentId);
        return $assignment === null
            ? ApiResponse::error($request, 'Assignment not found', 404)
            : ApiResponse::success($request, $assignment);
    }

    public function showDraft(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return ApiResponse::error($request, 'Invalid assignment ID', 400, ['assignmentId' => ['Must be a UUID.']]);
        }
        $studentId = (string) $request->attributes->get('auth.userId');
        if ($this->assignmentService->detail($studentId, $assignmentId) === null) {
            return ApiResponse::error($request, 'Assignment not found', 404);
        }

        return ApiResponse::success($request, $this->assignmentService->draft($studentId, $assignmentId));
    }

    public function saveDraft(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return ApiResponse::error($request, 'Invalid assignment ID', 400, ['assignmentId' => ['Must be a UUID.']]);
        }
        $input = ApiValidation::validate($request, $request->all(), ['content' => ['required', 'string', 'max:100000']], ['content']);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->operationResponse($request, $this->assignmentService->saveDraft(
            (string) $request->attributes->get('auth.userId'), $assignmentId, $input['content'],
        ));
    }

    public function updateDraft(Request $request, string $draftId): JsonResponse
    {
        if (! Str::isUuid($draftId)) {
            return ApiResponse::error($request, 'Invalid draft ID', 400, ['draftId' => ['Must be a UUID.']]);
        }
        $input = ApiValidation::validate($request, $request->all(), ['content' => ['required', 'string', 'max:100000']], ['content']);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->operationResponse($request, $this->assignmentService->updateDraft(
            (string) $request->attributes->get('auth.userId'), $draftId, $input['content'],
        ));
    }

    public function deleteDraft(Request $request, string $draftId): JsonResponse
    {
        if (! Str::isUuid($draftId)) {
            return ApiResponse::error($request, 'Invalid draft ID', 400, ['draftId' => ['Must be a UUID.']]);
        }
        $result = $this->assignmentService->deleteDraft((string) $request->attributes->get('auth.userId'), $draftId);
        if ($result['kind'] !== 'ok') {
            return ApiResponse::error($request, 'Assignment or draft not found', 404);
        }

        return response()->json(null, 204)->header('X-Request-ID', (string) $request->attributes->get('requestId'));
    }

    public function submit(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return ApiResponse::error($request, 'Invalid assignment ID', 400, ['assignmentId' => ['Must be a UUID.']]);
        }
        $input = ApiValidation::validate($request, $request->all(), [
            'content' => ['required', 'string', 'max:100000', 'regex:/\S/'],
        ], ['content']);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $result = $this->assignmentService->submit((string) $request->attributes->get('auth.userId'), $assignmentId, $input['content']);
        if ($result['kind'] === 'not_found') {
            return ApiResponse::error($request, 'Assignment or draft not found', 404);
        }
        if ($result['kind'] === 'conflict') {
            return ApiResponse::error($request, (string) $result['message'], 409);
        }

        $submission = $result['value'];
        return ApiResponse::success($request, [
            'id' => $submission['id'],
            'assignmentId' => $submission['assignment_id'],
            'content' => $submission['content'],
            'status' => $submission['status'],
            'submittedAt' => $submission['submitted_at'],
            'isLate' => $submission['is_late'],
            'attachments' => [],
        ], 201);
    }

    public function showSubmission(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return ApiResponse::error($request, 'Invalid assignment ID', 400, ['assignmentId' => ['Must be a UUID.']]);
        }
        $studentId = (string) $request->attributes->get('auth.userId');
        if ($this->assignmentService->detail($studentId, $assignmentId) === null) {
            return ApiResponse::error($request, 'Assignment not found', 404);
        }
        $submission = $this->assignmentService->submission($studentId, $assignmentId);
        if ($submission !== null) {
            $submission = [
                'id' => $submission['id'],
                'assignmentId' => $submission['assignment_id'],
                'content' => $submission['content'],
                'status' => $submission['status'],
                'submittedAt' => $submission['submitted_at'],
                'isLate' => $submission['is_late'],
                'attachments' => [],
            ];
        }

        return ApiResponse::success($request, $submission);
    }

    /** @param array{kind: string, value?: array<string, mixed>, message?: string} $result */
    private function operationResponse(Request $request, array $result): JsonResponse
    {
        if ($result['kind'] === 'not_found') {
            return ApiResponse::error($request, 'Assignment or draft not found', 404);
        }
        if ($result['kind'] === 'conflict') {
            return ApiResponse::error($request, (string) $result['message'], 409);
        }

        return ApiResponse::success($request, $result['value'] ?? null);
    }
}
