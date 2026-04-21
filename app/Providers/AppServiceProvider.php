<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('link-resolve', function (Request $request) {
            return [
                // DoS protection: 120 requests per minute per IP across all codes
                Limit::perMinute(120)->by($request->ip()),
                // Click inflation: 8 requests per minute per IP per link code
                Limit::perMinute(8)->by($request->ip().':'.$request->route('code')),
            ];
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
