<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class ScheduleRepository
{
    public function isStudentEnrolledInSemester(string $studentId, string $semesterId): bool
    {
        return DB::table('enrollments')->where('student_id', $studentId)->where('semester_id', $semesterId)->exists();
    }

    /** @return list<array<string, mixed>> */
    public function weeklySessions(string $studentId, ?string $semesterId): array
    {
        return DB::table('class_sessions as cs')
            ->join('courses as c', 'c.id', '=', 'cs.course_id')
            ->join('enrollments as e', function ($join) use ($studentId): void {
                $join->on('e.course_id', '=', 'c.id')->where('e.student_id', '=', $studentId);
            })
            ->join('semesters as s', function ($join) use ($semesterId): void {
                $join->on('s.id', '=', 'e.semester_id');
                if ($semesterId === null) {
                    $join->where('s.is_active', '=', true);
                } else {
                    $join->where('e.semester_id', '=', $semesterId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('cs.semester_id')->orWhereColumn('cs.semester_id', 's.id');
            })
            ->orderBy('cs.day_of_week')->orderBy('cs.start_time')->orderBy('c.course_code')->orderBy('cs.id')
            ->get(['cs.id', 'cs.course_id', 'c.course_code', 'c.course_name', 'c.instructor_name', 'cs.day_of_week', 'cs.start_time', 'cs.end_time', 'cs.room', 'cs.meeting_url', 'cs.semester_id'])
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }
}
