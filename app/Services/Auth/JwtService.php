<?php

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

final class JwtService
{
    public function issue(string $userId, string $role): string
    {
        $secret = (string) config('services.campus.jwt_secret');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must contain at least 32 characters.');
        }

        $now = time();
        $lifetime = (string) config('services.campus.jwt_expires_in', '15m');
        preg_match('/^(\d+)([smhd])$/', $lifetime, $matches);
        $multipliers = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];
        $expiresAt = $now + ((int) ($matches[1] ?? 15) * ($multipliers[$matches[2] ?? 'm'] ?? 60));

        return JWT::encode([
            'iss' => (string) config('services.campus.jwt_issuer'),
            'aud' => (string) config('services.campus.jwt_audience'),
            'sub' => $userId,
            'role' => $role,
            'tokenType' => 'access',
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ], $secret, 'HS256');
    }

    public function verify(string $token): ?object
    {
        $secret = (string) config('services.campus.jwt_secret');
        if (strlen($secret) < 32) {
            return null;
        }

        try {
            $claims = JWT::decode($token, new Key($secret, 'HS256'));
            $audience = $claims->aud ?? null;
            $audienceMatches = is_string($audience)
                ? hash_equals((string) config('services.campus.jwt_audience'), $audience)
                : (is_array($audience) && in_array((string) config('services.campus.jwt_audience'), $audience, true));

            if (
                ($claims->tokenType ?? null) !== 'access'
                || ! hash_equals((string) config('services.campus.jwt_issuer'), (string) ($claims->iss ?? ''))
                || ! $audienceMatches
                || ! is_string($claims->sub ?? null)
                || ! in_array($claims->role ?? null, ['student', 'instructor', 'admin'], true)
            ) {
                return null;
            }

            return $claims;
        } catch (Throwable) {
            return null;
        }
    }
}
