<?php

namespace App\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AssignmentRepository
{
    /** @return list<array<string, mixed>> */
    public function listForStudent(string $studentId, bool $upcomingOnly = false): array
    {
        return DB::table('assignments as a')
            ->join('courses as c', 'c.id', '=', 'a.course_id')
            ->join('enrollments as e', function ($join) use ($studentId): void {
                $join->on('e.course_id', '=', 'a.course_id')->where('e.student_id', '=', $studentId);
            })
            ->join('semesters as s', function ($join): void {
                $join->on('s.id', '=', 'e.semester_id')->where('s.is_active', '=', true);
            })
            ->leftJoin('submissions as sub', function ($join) use ($studentId): void {
                $join->on('sub.assignment_id', '=', 'a.id')->where('sub.student_id', '=', $studentId);
            })
            ->when($upcomingOnly, static fn ($query) => $query->where('a.due_at', '>', now('UTC'))->whereNull('sub.id'))
            ->orderBy('a.due_at')->orderBy('c.course_code')->orderBy('a.id')
            ->when($upcomingOnly, static fn ($query) => $query->limit(20))
            ->get([
                'a.id', 'a.course_id', 'c.course_code', 'c.course_name', 'a.title', 'a.instructions', 'a.due_at', 'a.max_score',
                'sub.status as submission_status', 'sub.submitted_at',
                DB::raw('CASE WHEN sub.id IS NULL THEN NULL ELSE sub.submitted_at > a.due_at END AS is_late'),
            ])
            ->map(static function (object $row): array {
                $item = (array) $row;
                $item['is_late'] = $item['is_late'] === null ? null : (bool) $item['is_late'];
                return $item;
            })
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function findForStudent(string $studentId, string $assignmentId): ?array
    {
        $row = DB::table('assignments as a')->join('courses as c', 'c.id', '=', 'a.course_id')
            ->where('a.id', $assignmentId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })
            ->first(['a.id', 'a.course_id', 'c.course_code', 'c.course_name', 'a.title', 'a.instructions', 'a.due_at', 'a.max_score']);

        return $row ? (array) $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findDraft(string $studentId, string $assignmentId): ?array
    {
        $row = DB::table('drafts as d')->where('d.assignment_id', $assignmentId)->where('d.student_id', $studentId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('assignments as a')->join('enrollments as e', 'e.course_id', '=', 'a.course_id')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('a.id', 'd.assignment_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })
            ->whereNotExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('submissions as sub')->whereColumn('sub.assignment_id', 'd.assignment_id')->where('sub.student_id', $studentId);
            })
            ->first(['d.id', 'd.assignment_id', 'd.content', 'd.created_at', 'd.updated_at']);

        return $row ? (array) $row : null;
    }

    /** @return array{kind: string, value?: array<string, mixed>, message?: string} */
    public function saveDraft(string $studentId, string $assignmentId, string $content): array
    {
        return DB::transaction(function () use ($studentId, $assignmentId, $content): array {
            $this->lockStudentAssignment($studentId, $assignmentId);
            if (! $this->isActiveEnrollment($studentId, $assignmentId)) {
                return ['kind' => 'not_found'];
            }
            if (DB::table('submissions')->where('assignment_id', $assignmentId)->where('student_id', $studentId)->exists()) {
                return ['kind' => 'conflict', 'message' => 'A submitted assignment cannot be edited as a draft'];
            }

            $now = now('UTC');
            $draft = DB::table('drafts')->where('assignment_id', $assignmentId)->where('student_id', $studentId)->first();
            if ($draft === null) {
                $id = (string) Str::uuid();
                DB::table('drafts')->insert([
                    'id' => $id, 'assignment_id' => $assignmentId, 'student_id' => $studentId,
                    'content' => $content, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                $id = $draft->id;
                DB::table('drafts')->where('id', $id)->update(['content' => $content, 'updated_at' => $now]);
            }

            return ['kind' => 'ok', 'value' => $this->findDraft($studentId, $assignmentId)];
        });
    }

    /** @return array{kind: string, value?: array<string, mixed>, message?: string} */
    public function updateDraft(string $studentId, string $draftId, string $content): array
    {
        return DB::transaction(function () use ($studentId, $draftId, $content): array {
            $draft = DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)->first();
            if ($draft === null) {
                return ['kind' => 'not_found'];
            }
            $this->lockStudentAssignment($studentId, $draft->assignment_id);
            $draft = DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)->lockForUpdate()->first();
            if ($draft === null) {
                return ['kind' => 'not_found'];
            }
            if (! $this->isActiveEnrollment($studentId, $draft->assignment_id) || $this->hasSubmission($studentId, $draft->assignment_id)) {
                return ['kind' => 'not_found'];
            }

            DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)
                ->update(['content' => $content, 'updated_at' => now('UTC')]);
            return ['kind' => 'ok', 'value' => (array) DB::table('drafts')->where('id', $draftId)->first(['id', 'assignment_id', 'content', 'created_at', 'updated_at'])];
        });
    }

    /** @return array{kind: string, message?: string} */
    public function deleteDraft(string $studentId, string $draftId): array
    {
        return DB::transaction(function () use ($studentId, $draftId): array {
            $draft = DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)->first();
            if ($draft === null) {
                return ['kind' => 'not_found'];
            }
            $this->lockStudentAssignment($studentId, $draft->assignment_id);
            $draft = DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)->lockForUpdate()->first();
            if ($draft === null) {
                return ['kind' => 'not_found'];
            }
            if (! $this->isActiveEnrollment($studentId, $draft->assignment_id) || $this->hasSubmission($studentId, $draft->assignment_id)) {
                return ['kind' => 'not_found'];
            }

            DB::table('drafts')->where('id', $draftId)->where('student_id', $studentId)->delete();
            return ['kind' => 'ok'];
        });
    }

    /** @return array{kind: string, value?: array<string, mixed>, message?: string} */
    public function submit(string $studentId, string $assignmentId, string $content): array
    {
        return DB::transaction(function () use ($studentId, $assignmentId, $content): array {
            $this->lockStudentAssignment($studentId, $assignmentId);
            $assignment = DB::table('assignments as a')
                ->where('a.id', $assignmentId)
                ->whereExists(function ($query) use ($studentId): void {
                    $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                        ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
                })
                ->first(['a.id', 'a.due_at']);
            if ($assignment === null) {
                return ['kind' => 'not_found'];
            }

            $existing = DB::table('submissions as sub')->leftJoin('grades as g', 'g.submission_id', '=', 'sub.id')
                ->where('sub.assignment_id', $assignmentId)->where('sub.student_id', $studentId)->lockForUpdate()->first(['sub.*', 'g.id as grade_id']);
            if ($existing !== null && ($existing->status === 'graded' || $existing->grade_id !== null)) {
                return ['kind' => 'conflict', 'message' => 'A graded submission cannot be replaced'];
            }

            $now = now('UTC');
            $status = $now->greaterThan($assignment->due_at) ? 'late' : 'submitted';
            if ($existing === null) {
                $id = (string) Str::uuid();
                DB::table('submissions')->insert([
                    'id' => $id, 'assignment_id' => $assignmentId, 'student_id' => $studentId,
                    'content' => $content, 'status' => $status, 'submitted_at' => $now,
                ]);
                $submittedAt = $now;
            } else {
                $id = $existing->id;
                $submittedAt = $existing->submitted_at;
                if ($existing->content !== $content) {
                    DB::table('submissions')->where('id', $id)->update([
                        'content' => $content, 'status' => $status, 'submitted_at' => $now,
                    ]);
                    $submittedAt = $now;
                }
            }

            DB::table('drafts')->where('assignment_id', $assignmentId)->where('student_id', $studentId)->delete();
            return ['kind' => 'ok', 'value' => [
                'id' => $id,
                'assignment_id' => $assignmentId,
                'content' => $content,
                'status' => $existing !== null && $existing->content === $content ? $existing->status : $status,
                'submitted_at' => $submittedAt,
                'is_late' => CarbonImmutable::parse($submittedAt, 'UTC')->greaterThan($assignment->due_at),
            ]];
        });
    }

    /** @return array<string, mixed>|null */
    public function findSubmission(string $studentId, string $assignmentId): ?array
    {
        $row = DB::table('submissions as sub')->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->where('sub.assignment_id', $assignmentId)->where('sub.student_id', $studentId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })
            ->first(['sub.id', 'sub.assignment_id', 'sub.content', 'sub.status', 'sub.submitted_at', DB::raw('(sub.submitted_at > a.due_at) AS is_late')]);

        if ($row === null) {
            return null;
        }
        $data = (array) $row;
        $data['is_late'] = (bool) $data['is_late'];
        return $data;
    }

    private function lockStudentAssignment(string $studentId, string $assignmentId): void
    {
        DB::selectOne("SELECT pg_advisory_xact_lock(hashtextextended(?::text || ':' || ?::text, 0))", [$studentId, $assignmentId]);
    }

    private function isActiveEnrollment(string $studentId, string $assignmentId): bool
    {
        return DB::table('assignments as a')->where('a.id', $assignmentId)
            ->whereExists(function ($query) use ($studentId): void {
                $query->selectRaw('1')->from('enrollments as e')->join('semesters as s', 's.id', '=', 'e.semester_id')
                    ->whereColumn('e.course_id', 'a.course_id')->where('e.student_id', $studentId)->where('s.is_active', true);
            })->exists();
    }

    private function hasSubmission(string $studentId, string $assignmentId): bool
    {
        return DB::table('submissions')->where('assignment_id', $assignmentId)->where('student_id', $studentId)->exists();
    }
}
