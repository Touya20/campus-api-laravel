<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('program', 160)->nullable();
            $table->string('year_level', 40)->nullable();
            $table->string('section', 80)->nullable();
            $table->timestampsTz();
        });

        Schema::create('semesters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE semesters ADD CONSTRAINT semesters_date_order CHECK (end_date >= start_date)');
        DB::statement('CREATE UNIQUE INDEX one_active_semester ON semesters(is_active) WHERE is_active');

        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('course_code')->unique();
            $table->string('course_name');
            $table->text('description')->nullable();
            $table->string('instructor_name')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('semesters');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['student_id', 'course_id', 'semester_id']);
            $table->index(['student_id', 'semester_id']);
            $table->index(['course_id', 'semester_id']);
        });

        Schema::create('modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->index(['course_id', 'position']);
        });
        DB::statement('ALTER TABLE modules ADD CONSTRAINT modules_position_nonnegative CHECK (position >= 0)');

        Schema::create('learning_materials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('file_url');
            $table->timestampsTz();
            $table->index(['module_id', 'created_at']);
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestampTz('due_at');
            $table->decimal('max_score', 8, 2)->nullable();
            $table->timestampsTz();
            $table->index(['course_id', 'due_at']);
            $table->index('due_at');
        });
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_max_score_positive CHECK (max_score IS NULL OR max_score > 0)');

        Schema::create('drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('content')->default('');
            $table->timestampsTz();
            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'updated_at']);
        });

        Schema::create('submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->text('file_url')->nullable();
            $table->string('status');
            $table->timestampTz('submitted_at')->useCurrent();
            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'submitted_at']);
        });
        DB::statement("ALTER TABLE submissions ADD CONSTRAINT submissions_status_allowed CHECK (status IN ('submitted', 'late', 'graded'))");
        DB::statement('ALTER TABLE submissions ADD CONSTRAINT submissions_has_content CHECK (content IS NOT NULL OR file_url IS NOT NULL)');

        Schema::create('grades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->unique()->constrained('submissions')->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2);
            $table->text('feedback')->nullable();
            $table->boolean('is_released')->default(false);
            $table->timestampTz('graded_at')->useCurrent();
        });
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score_bounds CHECK (score >= 0 AND max_score > 0 AND score <= max_score)');

        Schema::create('class_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->smallInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->text('meeting_url')->nullable();
            $table->foreignUuid('semester_id')->nullable()->constrained('semesters')->cascadeOnDelete();
            $table->index(['course_id', 'semester_id', 'day_of_week', 'start_time']);
        });
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_day_bounds CHECK (day_of_week BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_time_order CHECK (end_time > start_time)');

        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content');
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->timestampsTz();
            $table->index('created_at');
            $table->index(['course_id', 'created_at']);
            $table->index(['created_at', 'id']);
        });

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('read_at')->useCurrent();
            $table->unique(['announcement_id', 'student_id']);
            $table->index(['student_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('drafts');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('enrollments');
        DB::statement('DROP INDEX IF EXISTS one_active_semester');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('student_profiles');
    }
};
