<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->attributes->get('auth.role') !== $role) {
            return ApiResponse::error($request, 'Forbidden', 403);
        }

        return $next($request);
    }
}
