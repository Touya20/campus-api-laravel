<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService, private readonly JwtService $jwtService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23505', '23000'], true)) {
                return ApiResponse::error($request, 'Unable to create an account with those details', 409);
            }

            throw $exception;
        }

        return ApiResponse::success($request, [
            'user' => $this->publicUser($user),
            'accessToken' => $this->jwtService->issue($user->id, 'student'),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $input = $request->validated();
        $user = $this->authService->login($input['email'], $input['password']);

        if ($user === null) {
            return ApiResponse::error($request, 'Invalid email or password', 401);
        }

        return ApiResponse::success($request, [
            'user' => $this->publicUser($user),
            'accessToken' => $this->jwtService->issue($user->id, $user->role),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Discard the bearer token on the client',
        ])->header('X-Request-ID', (string) $request->attributes->get('requestId'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->activeUser((string) $request->attributes->get('auth.userId'));
        if ($user === null) {
            return ApiResponse::error($request, 'User not found', 404);
        }

        return ApiResponse::success($request, ['user' => $this->publicUser($user)]);
    }

    /** @return array{id: string, studentNumber: string, fullName: string, email: string, role: string} */
    private function publicUser(\App\Models\User $user): array
    {
        return [
            'id' => $user->id,
            'studentNumber' => $user->student_number,
            'fullName' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
