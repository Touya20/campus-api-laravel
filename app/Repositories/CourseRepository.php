<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class CourseRepository
{
    /** @return list<array<string, mixed>> */
    public function listForStudent(string $studentId, bool $activeOnly, int $page, int $limit): array
    {
        return DB::table('enrollments as e')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->join('semesters as s', 's.id', '=', 'e.semester_id')
            ->where('e.student_id', $studentId)
            ->when($activeOnly, static fn ($query) => $query->where('s.is_active', true))
            ->orderByDesc('s.start_date')->orderBy('c.course_code')->orderBy('c.id')->orderBy('e.id')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get(['c.*', 'e.semester_id', 's.name as semester_name'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function findEnrolledCourse(string $studentId, string $courseId): ?array
    {
        $course = DB::table('courses as c')
            ->where('c.id', $courseId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->whereColumn('e.course_id', 'c.id')->where('e.student_id', $studentId);
            })
            ->select(['c.id', 'c.course_code', 'c.course_name', 'c.description', 'c.instructor_name', 'c.created_at'])
            ->first();

        return $course ? (array) $course : null;
    }

    public function isEnrolled(string $studentId, string $courseId, bool $activeOnly = false): bool
    {
        return DB::table('enrollments as e')
            ->join('semesters as s', 's.id', '=', 'e.semester_id')
            ->where('e.student_id', $studentId)->where('e.course_id', $courseId)
            ->when($activeOnly, static fn ($query) => $query->where('s.is_active', true))
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    public function modules(string $courseId): array
    {
        return DB::table('modules')->where('course_id', $courseId)
            ->orderBy('position')->orderBy('title')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    /** @return list<array<string, mixed>> */
    public function materials(string $courseId): array
    {
        return DB::table('learning_materials as lm')->join('modules as m', 'm.id', '=', 'lm.module_id')
            ->where('m.course_id', $courseId)
            ->orderBy('m.position')->orderBy('m.title')->orderBy('m.id')->orderBy('lm.created_at')->orderBy('lm.id')
            ->get(['lm.id', 'lm.module_id', 'm.title as module_title', 'lm.title', 'lm.file_url', 'lm.created_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }
}
