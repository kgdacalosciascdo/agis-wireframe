<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request) {
            $employeeId = Str::upper(trim((string) $request->input('employeeId')));
            $ipAddress = $request->ip();
            $blockedResponse = fn () => response()->json([
                'success' => false,
                'message' => 'Too many sign-in attempts. Please wait one minute and try again.',
            ], 429);

            return [
                Limit::perMinute(5)
                    ->by('login-user:'.$employeeId.'|'.$ipAddress)
                    ->response($blockedResponse),
                Limit::perMinute(30)
                    ->by('login-ip:'.$ipAddress)
                    ->response($blockedResponse),
            ];
        });
    }
}
