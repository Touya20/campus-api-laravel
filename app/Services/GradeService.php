<?php

namespace App\Services;

use App\Repositories\GradeRepository;

final class GradeService
{
    public function __construct(private readonly GradeRepository $gradeRepository)
    {
    }

    public function releasedGrades(string $studentId, ?string $courseId = null): array
    {
        return $this->gradeRepository->releasedGrades($studentId, $courseId);
    }

    public function isEnrolledInCourse(string $studentId, string $courseId): bool
    {
        return $this->gradeRepository->isEnrolledInCourse($studentId, $courseId);
    }

    public function summary(string $studentId): array
    {
        return $this->gradeRepository->summary($studentId);
    }
}
