<?php

use App\Http\Api\ProblemDetailsRenderer;
use App\Http\Middleware\EnsureTechnicalHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only the proxy / load-balancer subnets that actually front the
        // app — never '*'. Trusting every client lets anyone spoof
        // X-Forwarded-For, forging the recorded click IP (and {{click.ip}} sent
        // to callbacks) and bypassing the per-IP link-resolve rate limiter.
        // Narrow these CIDRs to your real reverse-proxy range in production.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        // The API lives on the technical host only; reject other short-link
        // hosts before auth/throttle so a wrong-host call never burns the
        // per-token rate-limit budget. Filament and Horizon are guarded by the
        // same middleware in their own route registrations.
        $middleware->prependToGroup('api', EnsureTechnicalHost::class);

        // Sanctum token-ability gates for API routes.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // /api/v1 speaks RFC 7807 (application/problem+json) with stable
        // machine codes; legacy unversioned /api/* keeps Laravel's default
        // JSON error shape until clients migrate (docs/08-decisions.md).
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/v1', 'api/v1/*')) {
                return ProblemDetailsRenderer::render($e);
            }

            return null;
        });
    })->create();
