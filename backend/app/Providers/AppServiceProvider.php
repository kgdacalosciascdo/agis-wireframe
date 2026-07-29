<?php

namespace App\Providers;

use App\Services\RuntimeConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registers application-wide framework customizations and boot-time behavior.
 */
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
            $configuration = app(RuntimeConfiguration::class);
            $employeeId = Str::upper(trim((string) $request->input('employeeId')));
            $ipAddress = $request->ip();
            $blockedResponse = fn () => response()->json([
                'success' => false,
                'message' => 'Too many sign-in attempts. Please wait one minute and try again.',
            ], 429);

            return [
                Limit::perMinute($configuration->failedLoginLimit())
                    ->by('login-user:'.$employeeId.'|'.$ipAddress)
                    ->response($blockedResponse),
                Limit::perMinute(30)
                    ->by('login-ip:'.$ipAddress)
                    ->response($blockedResponse),
            ];
        });
    }
}
