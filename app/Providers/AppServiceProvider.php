<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentTimezone;
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

        FilamentTimezone::set(function (): ?string {
            $tz = request()->cookie('tz');

            return is_string($tz) && in_array($tz, timezone_identifiers_list(), true)
                ? $tz
                : null;
        });

        RateLimiter::for('link-resolve', function (Request $request) {
            return [
                // DoS protection: 120 requests per minute per IP across all codes
                Limit::perMinute(120)->by($request->ip()),
                // Click inflation: 8 requests per minute per IP per link code
                Limit::perMinute(8)->by($request->ip().':'.$request->route('code')),
            ];
        });

        // Storage-amplification protection for the authenticated create-link API:
        // each call can persist a link plus up to 50 rules and condition/url rows,
        // so cap it per owning service (the token's tokenable).
        RateLimiter::for('api-links', function (Request $request) {
            $service = $request->user();

            return Limit::perMinute(120)->by(
                $service ? 'service:'.$service->id : 'ip:'.$request->ip()
            );
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
