<?php

namespace App\Services;

use App\Repositories\AnnouncementRepository;

final class AnnouncementService
{
    public function __construct(private readonly AnnouncementRepository $announcementRepository)
    {
    }

    public function isEnrolledInActiveCourse(string $studentId, string $courseId): bool
    {
        return $this->announcementRepository->isEnrolledInActiveCourse($studentId, $courseId);
    }

    public function list(string $studentId, ?string $courseId, int $limit, int $offset): array
    {
        return $this->announcementRepository->listVisible($studentId, $courseId, $limit, $offset);
    }

    public function detail(string $studentId, string $announcementId): ?array
    {
        return $this->announcementRepository->findVisible($studentId, $announcementId);
    }

    public function markRead(string $studentId, string $announcementId): ?array
    {
        return $this->announcementRepository->markRead($studentId, $announcementId);
    }
}
