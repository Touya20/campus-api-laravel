<?php

namespace App\Services;

use App\Repositories\AssignmentRepository;

final class AssignmentService
{
    public function __construct(private readonly AssignmentRepository $assignmentRepository)
    {
    }

    public function list(string $studentId): array
    {
        return $this->assignmentRepository->listForStudent($studentId);
    }

    public function upcoming(string $studentId): array
    {
        return $this->assignmentRepository->listForStudent($studentId, true);
    }

    public function detail(string $studentId, string $assignmentId): ?array
    {
        return $this->assignmentRepository->findForStudent($studentId, $assignmentId);
    }

    public function draft(string $studentId, string $assignmentId): ?array
    {
        return $this->assignmentRepository->findDraft($studentId, $assignmentId);
    }

    public function saveDraft(string $studentId, string $assignmentId, string $content): array
    {
        return $this->assignmentRepository->saveDraft($studentId, $assignmentId, $content);
    }

    public function updateDraft(string $studentId, string $draftId, string $content): array
    {
        return $this->assignmentRepository->updateDraft($studentId, $draftId, $content);
    }

    public function deleteDraft(string $studentId, string $draftId): array
    {
        return $this->assignmentRepository->deleteDraft($studentId, $draftId);
    }

    public function submit(string $studentId, string $assignmentId, string $content): array
    {
        return $this->assignmentRepository->submit($studentId, $assignmentId, $content);
    }

    public function submission(string $studentId, string $assignmentId): ?array
    {
        return $this->assignmentRepository->findSubmission($studentId, $assignmentId);
    }
}
