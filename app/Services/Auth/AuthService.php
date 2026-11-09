<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthService
{
    private ?string $dummyHash = null;

    /** @param array<string, mixed> $input */
    public function register(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            $user = User::query()->create([
                'id' => (string) Str::uuid(),
                'student_number' => $input['studentNumber'],
                'full_name' => $input['fullName'],
                'email' => $input['email'],
                'password_hash' => Hash::driver('argon2id')->make($input['password']),
                'role' => 'student',
                'is_active' => true,
            ]);

            DB::table('student_profiles')->insert([
                'user_id' => $user->id,
                'program' => $input['program'] ?? null,
                'year_level' => isset($input['yearLevel']) ? (string) $input['yearLevel'] : null,
                'section' => $input['section'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });
    }

    public function login(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        $hash = $user?->password_hash ?? $this->dummyHash();
        $valid = Hash::driver('argon2id')->check($password, (string) $hash);

        return $valid && $user !== null ? $user : null;
    }

    public function activeUser(string $userId): ?User
    {
        return User::query()->where('id', $userId)->where('is_active', true)->first();
    }

    private function dummyHash(): string
    {
        return $this->dummyHash ??= Hash::driver('argon2id')->make('campus-api-invalid-account-check');
    }
}
