<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the short-link hosts — every host in APP_HOST except the
 * technical one.
 *
 * The technical host (the host of APP_URL) serves the admin panel, API and
 * Horizon only; it must never resolve a short code. This is the mirror of
 * {@see EnsureTechnicalHost}: a request on the technical host — or on any host
 * not in the configured short-link allowlist — 404s, so neither the technical
 * domain nor an unknown host pointed at the server resolves links. When no hosts
 * are configured the guard is a no-op, so a missing APP_HOST never 404s every
 * short link.
 */
class EnsureShortLinkHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shortLinkHosts = $this->shortLinkHosts();

        if ($shortLinkHosts !== [] && ! in_array(strtolower($request->host()), $shortLinkHosts, true)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * The short-link allowlist: every APP_HOST entry except the technical host,
     * lower-cased for a case-insensitive host comparison.
     *
     * @return list<string>
     */
    private function shortLinkHosts(): array
    {
        $technicalHost = config('app.technical_host');
        $technicalHost = $technicalHost !== null ? strtolower((string) $technicalHost) : null;

        return Collection::make(config('app.hosts'))
            ->map(fn (string $host): string => strtolower($host))
            ->reject(fn (string $host): bool => $host === $technicalHost)
            ->values()
            ->all();
    }
}
