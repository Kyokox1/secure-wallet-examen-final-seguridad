<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\ServiceProvider as ServiceProviderAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // RS-08: rate limiting explícito por endpoint sensible.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email', $request->ip()));
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('transfers', function (Request $request) {
            $user = $request->attributes->get('auth_user');
            return Limit::perMinute(10)->by($user?->id ?: $request->ip());
        });
    }
}
