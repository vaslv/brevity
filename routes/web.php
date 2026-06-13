<?php

use App\Http\Controllers\ResolveLink;
use App\Http\Middleware\EnsureShortLinkHost;
use Illuminate\Support\Facades\Route;

// Short links resolve on the short-link domains only. EnsureShortLinkHost 404s
// the technical host (which serves the admin panel, API and Horizon) and any
// unknown host, and runs before throttle so a wrong-host hit never burns the
// per-IP rate-limit budget. The code constraint keeps junk single-segment paths
// (favicon.ico, robots.txt, …) 404-ing at the router. Filament/Horizon/health
// routes are registered earlier and still take precedence for their own paths.
Route::get('/{code}', ResolveLink::class)
    ->where('code', '[A-Za-z0-9]{5,16}')
    ->middleware([EnsureShortLinkHost::class, 'throttle:link-resolve']);
