<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\JwtService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateJwt
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token ? $this->jwtService->verify($token) : null;

        if ($claims === null) {
            return ApiResponse::error($request, $token ? 'Invalid or expired access token' : 'Authentication required', 401);
        }

        $user = User::query()->where('id', (string) $claims->sub)->where('is_active', true)->first(['id', 'role']);
        if ($user === null || $user->role !== (string) $claims->role) {
            return ApiResponse::error($request, 'Invalid or expired access token', 401);
        }

        $request->attributes->set('auth.userId', (string) $user->id);
        $request->attributes->set('auth.role', (string) $user->role);

        return $next($request);
    }
}
