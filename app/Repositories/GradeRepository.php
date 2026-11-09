<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class GradeRepository
{
    /** @return list<array<string, mixed>> */
    public function releasedGrades(string $studentId, ?string $courseId = null): array
    {
        return DB::table('grades as g')
            ->join('submissions as sub', 'sub.id', '=', 'g.submission_id')
            ->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->join('courses as c', 'c.id', '=', 'a.course_id')
            ->where('g.is_released', true)->where('sub.student_id', $studentId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->whereColumn('e.course_id', 'c.id')->where('e.student_id', $studentId);
            })
            ->when($courseId !== null, static fn ($query) => $query->where('c.id', $courseId))
            ->orderByDesc('g.graded_at')->orderByDesc('g.id')
            ->get(['g.id', 'g.submission_id', 'a.title as assignment_title', 'c.id as course_id', 'c.course_code', 'c.course_name', 'g.score', 'g.max_score', 'g.feedback', 'g.graded_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    public function isEnrolledInCourse(string $studentId, string $courseId): bool
    {
        return DB::table('enrollments')->where('student_id', $studentId)->where('course_id', $courseId)->exists();
    }

    /** @return list<array<string, mixed>> */
    public function summary(string $studentId): array
    {
        $enrolledCourses = DB::table('enrollments')->select('course_id')->where('student_id', $studentId)->distinct();

        return DB::table('courses as c')
            ->joinSub($enrolledCourses, 'ec', 'ec.course_id', '=', 'c.id')
            ->leftJoin('assignments as a', 'a.course_id', '=', 'c.id')
            ->leftJoin('submissions as sub', function ($join) use ($studentId): void {
                $join->on('sub.assignment_id', '=', 'a.id')->where('sub.student_id', '=', $studentId);
            })
            ->leftJoin('grades as g', function ($join): void {
                $join->on('g.submission_id', '=', 'sub.id')->where('g.is_released', '=', true);
            })
            ->groupBy('c.id', 'c.course_code', 'c.course_name')
            ->orderBy('c.course_code')->orderBy('c.id')
            ->get([
                'c.id as course_id', 'c.course_code', 'c.course_name',
                DB::raw("COALESCE(SUM(g.score), 0)::text AS earned_points"),
                DB::raw("COALESCE(SUM(g.max_score), 0)::text AS possible_points"),
                DB::raw("CASE WHEN COALESCE(SUM(g.max_score), 0) = 0 THEN NULL ELSE ROUND(COALESCE(SUM(g.score), 0) / SUM(g.max_score) * 100, 2)::text END AS percentage"),
            ])
            ->map(static fn (object $row): array => (array) $row)->all();
    }
}
