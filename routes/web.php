<?php

use App\Http\Controllers\ResolveLink;
use Illuminate\Support\Facades\Route;

// Constrain the catch-all to the short-code shape so junk single-segment paths
// (favicon.ico, robots.txt, …) 404 at the router instead of hitting the
// resolver and burning the per-IP rate-limit budget. Filament/Horizon/health
// routes are registered earlier and still take precedence for their own paths.
Route::get('/{code}', ResolveLink::class)
    ->where('code', '[A-Za-z0-9]{5,8}')
    ->middleware('throttle:link-resolve');
