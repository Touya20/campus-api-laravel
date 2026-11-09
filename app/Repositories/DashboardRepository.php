<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class DashboardRepository
{
    /** @return array<string, mixed> */
    public function summary(string $studentId): array
    {
        $courseCount = DB::table('enrollments as e')
            ->join('semesters as s', 's.id', '=', 'e.semester_id')
            ->where('e.student_id', $studentId)
            ->where('s.is_active', true)
            ->count();

        $pendingAssignments = DB::table('assignments as a')
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })
            ->whereNotExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('submissions as sub')->whereColumn('sub.assignment_id', 'a.id')->where('sub.student_id', $studentId);
            })
            ->count();

        return [
            'courseCount' => (int) $courseCount,
            'pendingAssignments' => (int) $pendingAssignments,
            'recentAnnouncements' => $this->recentAnnouncements($studentId, 5),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function upcomingAssignments(string $studentId, int $limit = 20): array
    {
        return DB::table('assignments as a')
            ->join('courses as c', 'c.id', '=', 'a.course_id')
            ->where('a.due_at', '>', now('UTC'))
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })
            ->whereNotExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('submissions as sub')->whereColumn('sub.assignment_id', 'a.id')->where('sub.student_id', $studentId);
            })
            ->orderBy('a.due_at')->orderBy('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.course_id', 'c.course_code', 'a.title', 'a.due_at'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function recentAnnouncements(string $studentId, int $limit): array
    {
        return DB::table('announcements as a')
            ->leftJoin('courses as c', 'c.id', '=', 'a.course_id')
            ->leftJoin('announcement_reads as ar', function ($join) use ($studentId): void {
                $join->on('ar.announcement_id', '=', 'a.id')->where('ar.student_id', '=', $studentId);
            })
            ->where(function ($query) use ($studentId): void {
                $query->whereNull('a.course_id')->orWhereExists(function ($enrollment) use ($studentId): void {
                    $enrollment->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                        ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
                });
            })
            ->orderByDesc('a.created_at')->orderByDesc('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.title', 'a.course_id', 'c.course_code', 'a.created_at', DB::raw('COALESCE(ar.read_at IS NOT NULL, false) AS is_read')])
            ->map(static function (object $row): array {
                $data = (array) $row;
                $data['is_read'] = (bool) $data['is_read'];
                return $data;
            })
            ->all();
    }
}
