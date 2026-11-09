<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private readonly DashboardRepository $dashboardRepository)
    {
    }

    public function summary(string $studentId): array
    {
        return $this->dashboardRepository->summary($studentId);
    }

    public function upcoming(string $studentId): array
    {
        return $this->dashboardRepository->upcomingAssignments($studentId);
    }

    public function recentAnnouncements(string $studentId): array
    {
        return $this->dashboardRepository->recentAnnouncements($studentId, 10);
    }
}
