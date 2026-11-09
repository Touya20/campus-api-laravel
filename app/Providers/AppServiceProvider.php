<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', static fn (Request $request) => Limit::perMinutes(15, 10)->by($request->ip()));
        RateLimiter::for('sensitive', static fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->attributes->get('auth.userId') ?? $request->ip())));
        RateLimiter::for('chat', static fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->attributes->get('auth.userId') ?? $request->ip())));
    }
}
