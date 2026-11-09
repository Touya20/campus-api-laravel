<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Development seed data cannot be run in production.');
        }

        $password = (string) env('SEED_STUDENT_PASSWORD');
        if (strlen($password) < 12) {
            throw new \RuntimeException('Set SEED_STUDENT_PASSWORD to at least 12 characters before seeding.');
        }

        $ids = [
            'student' => '10000000-0000-4000-8000-000000000001',
            'semester' => '20000000-0000-4000-8000-000000000001',
            'course' => '30000000-0000-4000-8000-000000000001',
            'module' => '40000000-0000-4000-8000-000000000001',
            'material' => '41000000-0000-4000-8000-000000000001',
            'assignment' => '50000000-0000-4000-8000-000000000001',
            'gradedAssignment' => '50000000-0000-4000-8000-000000000002',
            'unreleasedAssignment' => '50000000-0000-4000-8000-000000000003',
            'draftAssignment' => '50000000-0000-4000-8000-000000000004',
            'lateAssignment' => '50000000-0000-4000-8000-000000000005',
            'submission' => '51000000-0000-4000-8000-000000000001',
            'unreleasedSubmission' => '51000000-0000-4000-8000-000000000002',
            'grade' => '52000000-0000-4000-8000-000000000001',
            'unreleasedGrade' => '52000000-0000-4000-8000-000000000002',
            'session' => '53000000-0000-4000-8000-000000000001',
            'announcement' => '60000000-0000-4000-8000-000000000001',
            'campusAnnouncement' => '60000000-0000-4000-8000-000000000002',
        ];

        // Each fixture is an idempotent upsert keyed by its reserved demo ID. Running
        // without one large transaction lets PostgreSQL report the original statement
        // error instead of masking subsequent queries with SQLSTATE 25P02. A rerun safely
        // completes any partially written demo fixture rows.
        {
            $now = now('UTC');

            // Never deactivate or rewrite a semester that belongs to the existing database.
            // Reuse its active semester for the demo enrollment; create a demo semester only
            // when the database does not have one yet.
            $activeSemesterId = DB::table('semesters')->where('is_active', true)->value('id');
            if ($activeSemesterId === null) {
                DB::table('semesters')->updateOrInsert(['id' => $ids['semester']], [
                    'name' => 'Demo Semester', 'start_date' => today()->subDays(30), 'end_date' => today()->addDays(150),
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $activeSemesterId = $ids['semester'];
            }

            // Avoid overwriting real records if a demo identifier or unique demo value was
            // already used by someone in this database.
            $demoUser = DB::table('users')->where('id', $ids['student'])->first();
            $userConflict = DB::table('users')
                ->where(function ($query) use ($ids): void {
                    $query->where('email', 'student@example.test')
                        ->orWhere('student_number', 'DEMO-001');
                })
                ->where('id', '<>', $ids['student'])
                ->exists();
            if ($demoUser === null && $userConflict) {
                throw new \RuntimeException('A user already uses the demo email or student number. Change the demo identity in DatabaseSeeder.php before seeding.');
            }
            if ($demoUser !== null && ($demoUser->email !== 'student@example.test' || $demoUser->student_number !== 'DEMO-001')) {
                throw new \RuntimeException('The reserved demo user ID is already assigned to another account. Change the demo IDs in DatabaseSeeder.php before seeding.');
            }
            $courseConflict = DB::table('courses')->where('course_code', 'CS-201')->where('id', '<>', $ids['course'])->exists();
            if ($courseConflict) {
                throw new \RuntimeException('A course already uses the demo course code CS-201. Change the demo course code in DatabaseSeeder.php before seeding.');
            }

            DB::table('users')->updateOrInsert(['id' => $ids['student']], [
                'student_number' => 'DEMO-001', 'full_name' => 'Alex Student', 'email' => 'student@example.test',
                'password_hash' => Hash::driver('argon2id')->make($password), 'role' => 'student', 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('student_profiles')->updateOrInsert(['user_id' => $ids['student']], [
                'program' => 'Computer Science', 'year_level' => 'Year 2', 'section' => 'A', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('courses')->updateOrInsert(['id' => $ids['course']], [
                'course_code' => 'CS-201', 'course_name' => 'Data Structures', 'description' => 'Demo course for local development',
                'instructor_name' => 'Taylor Instructor', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('enrollments')->updateOrInsert([
                'student_id' => $ids['student'], 'course_id' => $ids['course'], 'semester_id' => $activeSemesterId,
            ], ['id' => '31000000-0000-4000-8000-000000000001', 'created_at' => $now]);
            DB::table('modules')->updateOrInsert(['id' => $ids['module']], [
                'course_id' => $ids['course'], 'title' => 'Getting Started', 'description' => 'Demo learning module',
                'position' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('learning_materials')->updateOrInsert(['id' => $ids['material']], [
                'module_id' => $ids['module'], 'title' => 'Course guide', 'file_url' => 'https://example.test/course-guide',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $assignments = [
                ['assignment', 'Complexity reflection', 'Write a short reflection.', 14, 20],
                ['gradedAssignment', 'Algorithm analysis', 'Analyze time complexity.', -7, 30],
                ['unreleasedAssignment', 'Unreleased score fixture', 'Fictional unreleased-grade fixture.', -2, 20],
                ['draftAssignment', 'Draft workflow fixture', 'Fictional assignment for draft workflows.', 9, 10],
                ['lateAssignment', 'Late submission fixture', 'Fictional late-submission fixture.', -1, 10],
            ];
            foreach ($assignments as [$key, $title, $instructions, $daysFromNow, $maxScore]) {
                DB::table('assignments')->updateOrInsert(['id' => $ids[$key]], [
                    'course_id' => $ids['course'], 'title' => $title, 'instructions' => $instructions,
                    'due_at' => $now->copy()->addDays($daysFromNow), 'max_score' => $maxScore,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('submissions')->updateOrInsert(['id' => $ids['submission']], [
                'assignment_id' => $ids['gradedAssignment'], 'student_id' => $ids['student'],
                'content' => 'Fictional sample response for local development.', 'status' => 'graded', 'submitted_at' => $now->copy()->subDays(8),
            ]);
            DB::table('submissions')->updateOrInsert(['id' => $ids['unreleasedSubmission']], [
                'assignment_id' => $ids['unreleasedAssignment'], 'student_id' => $ids['student'],
                'content' => 'Fictional private grade-release fixture.', 'status' => 'graded', 'submitted_at' => $now->copy()->subDays(3),
            ]);
            DB::table('grades')->updateOrInsert(['id' => $ids['grade']], [
                'submission_id' => $ids['submission'], 'score' => 27, 'max_score' => 30,
                'feedback' => 'Good analysis; clarify the worst-case bound.', 'is_released' => true, 'graded_at' => $now->copy()->subDays(6),
            ]);
            DB::table('grades')->updateOrInsert(['id' => $ids['unreleasedGrade']], [
                'submission_id' => $ids['unreleasedSubmission'], 'score' => 19, 'max_score' => 20,
                'feedback' => 'Fictional unreleased feedback fixture.', 'is_released' => false, 'graded_at' => $now->copy()->subDays(2),
            ]);
            DB::table('class_sessions')->updateOrInsert(['id' => $ids['session']], [
                'course_id' => $ids['course'], 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '10:30',
                'room' => 'Room 204', 'semester_id' => $ids['semester'],
            ]);
            DB::table('announcements')->updateOrInsert(['id' => $ids['announcement']], [
                'title' => 'Welcome', 'content' => 'Welcome to the demo course.', 'course_id' => $ids['course'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('announcements')->updateOrInsert(['id' => $ids['campusAnnouncement']], [
                'title' => 'Campus notice', 'content' => 'Fictional campus-wide notice for local development.', 'course_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
