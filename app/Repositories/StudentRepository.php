<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class StudentRepository
{
    /** @return array<string, mixed>|null */
    public function findProfile(string $studentId): ?array
    {
        $profile = DB::table('users as u')
            ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.id', $studentId)
            ->where('u.is_active', true)
            ->where('u.role', 'student')
            ->select(['u.id', 'u.student_number', 'u.full_name', 'u.email', 'u.role', 'p.program', 'p.year_level', 'p.section'])
            ->first();

        return $profile ? (array) $profile : null;
    }

    /** @param array<string, mixed> $input @return array<string, mixed>|null */
    public function updateProfile(string $studentId, array $input): ?array
    {
        return DB::transaction(function () use ($studentId, $input): ?array {
            $user = DB::table('users')->where('id', $studentId)->where('is_active', true)->where('role', 'student')->first();
            if ($user === null) {
                return null;
            }

            if (array_key_exists('fullName', $input)) {
                DB::table('users')->where('id', $studentId)->update([
                    'full_name' => $input['fullName'],
                    'updated_at' => now(),
                ]);
            }

            $profileFields = [];
            foreach (['program' => 'program', 'yearLevel' => 'year_level', 'section' => 'section'] as $inputKey => $column) {
                if (array_key_exists($inputKey, $input)) {
                    $profileFields[$column] = $input[$inputKey];
                }
            }

            if ($profileFields !== []) {
                $existing = DB::table('student_profiles')->where('user_id', $studentId)->exists();
                if ($existing) {
                    $profileFields['updated_at'] = now();
                    DB::table('student_profiles')->where('user_id', $studentId)->update($profileFields);
                } else {
                    DB::table('student_profiles')->insert(array_merge([
                        'user_id' => $studentId,
                        'program' => null,
                        'year_level' => null,
                        'section' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $profileFields));
                }
            }

            return $this->findProfile($studentId);
        });
    }
}
