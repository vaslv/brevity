<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the technical host (the host of APP_URL).
 *
 * Every hostname in APP_HOST resolves to the same app container, but only the
 * technical domain (the host of APP_URL) may serve the admin panel, API and
 * Horizon. The other hosts are short-link domains only. Requests for guarded
 * routes on any other host 404 — we hide the panel/API rather than reveal that
 * it exists elsewhere. When no technical host is configured the guard is a
 * no-op, so a missing APP_URL never locks everyone out.
 */
class EnsureTechnicalHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $technicalHost = config('app.technical_host');

        if ($technicalHost !== null && strcasecmp($request->host(), (string) $technicalHost) !== 0) {
            abort(404);
        }

        return $next($request);
    }
}
