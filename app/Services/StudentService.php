<?php

namespace App\Services;

use App\Repositories\StudentRepository;

final class StudentService
{
    public function __construct(private readonly StudentRepository $studentRepository)
    {
    }

    public function getProfile(string $studentId): ?array
    {
        return $this->studentRepository->findProfile($studentId);
    }

    /** @param array<string, mixed> $input */
    public function updateProfile(string $studentId, array $input): ?array
    {
        return $this->studentRepository->updateProfile($studentId, $input);
    }
}
