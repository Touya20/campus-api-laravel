<?php

namespace App\Services;

use App\Repositories\CourseRepository;

final class CourseService
{
    public function __construct(private readonly CourseRepository $courseRepository)
    {
    }

    public function list(string $studentId, bool $activeOnly, int $page, int $limit): array
    {
        return $this->courseRepository->listForStudent($studentId, $activeOnly, $page, $limit);
    }

    public function detail(string $studentId, string $courseId): ?array
    {
        return $this->courseRepository->findEnrolledCourse($studentId, $courseId);
    }

    public function isEnrolled(string $studentId, string $courseId, bool $activeOnly = false): bool
    {
        return $this->courseRepository->isEnrolled($studentId, $courseId, $activeOnly);
    }

    public function modules(string $courseId): array
    {
        return $this->courseRepository->modules($courseId);
    }

    public function materials(string $courseId): array
    {
        $hosts = array_filter(array_map('trim', explode(',', (string) config('services.campus.material_allowed_hosts'))));

        return array_map(static function (array $material) use ($hosts): array {
            $url = $material['file_url'] ?? null;
            if (! is_string($url)) {
                $material['file_url'] = null;
                return $material;
            }

            $parts = parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $allowed = ($parts['scheme'] ?? null) === 'https'
                && ! isset($parts['user']) && ! isset($parts['pass'])
                && $host !== ''
                && in_array($host, array_map('strtolower', $hosts), true);
            if (! $allowed) {
                $material['file_url'] = null;
            }

            return $material;
        }, $this->courseRepository->materials($courseId));
    }
}
