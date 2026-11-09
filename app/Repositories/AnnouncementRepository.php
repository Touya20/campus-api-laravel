<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AnnouncementRepository
{
    public function isEnrolledInActiveCourse(string $studentId, string $courseId): bool
    {
        return DB::table('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
            ->where('e.student_id', $studentId)->where('e.course_id', $courseId)->where('s.is_active', true)->exists();
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function listVisible(string $studentId, ?string $courseId, int $limit, int $offset): array
    {
        $query = $this->visibleQuery($studentId, $courseId);
        $total = (clone $query)->count('a.id');
        $items = $query->orderByDesc('a.created_at')->orderByDesc('a.id')->offset($offset)->limit($limit)
            ->get(['a.id', 'a.title', 'a.content', 'a.course_id', 'c.course_code', 'a.created_at', 'a.updated_at', DB::raw('(ar.read_at IS NOT NULL) AS is_read'), 'ar.read_at'])
            ->map(static function (object $row): array {
                $item = (array) $row;
                $item['is_read'] = (bool) $item['is_read'];
                return $item;
            })->all();

        return ['items' => $items, 'total' => (int) $total];
    }

    /** @return array<string, mixed>|null */
    public function findVisible(string $studentId, string $announcementId): ?array
    {
        $row = $this->visibleQuery($studentId, null)->where('a.id', $announcementId)
            ->first(['a.id', 'a.title', 'a.content', 'a.course_id', 'c.course_code', 'a.created_at', 'a.updated_at', DB::raw('(ar.read_at IS NOT NULL) AS is_read'), 'ar.read_at']);
        if ($row === null) {
            return null;
        }
        $item = (array) $row;
        $item['is_read'] = (bool) $item['is_read'];
        return $item;
    }

    /** @return array<string, mixed>|null */
    public function markRead(string $studentId, string $announcementId): ?array
    {
        return DB::transaction(function () use ($studentId, $announcementId): ?array {
            if ($this->findVisible($studentId, $announcementId) === null) {
                return null;
            }

            DB::table('announcement_reads')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'announcement_id' => $announcementId,
                'student_id' => $studentId,
                'read_at' => now('UTC'),
            ]);

            $read = DB::table('announcement_reads')->where('announcement_id', $announcementId)->where('student_id', $studentId)->first();
            return [
                'announcement_id' => $announcementId,
                'is_read' => true,
                'read_at' => $read?->read_at,
            ];
        });
    }

    private function visibleQuery(string $studentId, ?string $courseId): \Illuminate\Database\Query\Builder
    {
        return DB::table('announcements as a')
            ->leftJoin('courses as c', 'c.id', '=', 'a.course_id')
            ->leftJoin('announcement_reads as ar', function ($join) use ($studentId): void {
                $join->on('ar.announcement_id', '=', 'a.id')->where('ar.student_id', '=', $studentId);
            })
            ->where(function ($query) use ($studentId, $courseId): void {
                if ($courseId !== null) {
                    $query->where('a.course_id', $courseId);
                    return;
                }

                $query->whereNull('a.course_id')->orWhereExists(function ($enrollment) use ($studentId): void {
                    $enrollment->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                        ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
                });
            });
    }
}
