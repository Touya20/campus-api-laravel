<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityBaseline;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function (): void {
            Route::middleware('api')->get('/health', static fn () => response()->json([
                'status' => 'ok',
                'service' => config('app.name'),
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
            ]));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
        $middleware->append(SecurityBaseline::class);
        $middleware->alias([
            'auth.jwt' => AuthenticateJwt::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid request',
                'requestId' => $request->attributes->get('requestId'),
                'errors' => $exception->errors(),
            ], 400)->header('X-Request-ID', (string) $request->attributes->get('requestId'));
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $message = match ($status) {
                400 => 'Invalid request',
                401 => 'Authentication required',
                403 => 'Request rejected',
                404 => 'Not found',
                405 => 'Method not allowed',
                413 => 'Request body too large',
                415 => 'Unsupported request content type',
                429 => 'Too many requests',
                default => 'Internal server error',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'requestId' => $request->attributes->get('requestId'),
            ], $status)->header('X-Request-ID', (string) $request->attributes->get('requestId'));
        });
    })->create();
