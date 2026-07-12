<?php

use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\DomainGroupController;
use App\Http\Controllers\Api\LinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:links:create', 'throttle:api-links'])->group(function () {
    Route::post('/links', [LinkController::class, 'store']);
});

// Read-only catalog endpoints. Same `links:create` ability gates API access (a
// link-creating client reads the domain/group catalog to build links), but a
// separate throttle bucket so reads never eat the create-link budget.
Route::middleware(['auth:sanctum', 'abilities:links:create', 'throttle:api-read'])->group(function () {
    Route::get('/domains', [DomainController::class, 'index']);
    Route::get('/domain-groups', [DomainGroupController::class, 'index']);
});

// Versioned API (docs/08-decisions.md, 2026-07-12): RFC 7807 errors and all
// new functionality live under /api/v1 (same controllers as legacy). The
// unversioned routes above are frozen legacy — old error format, old feature
// set, deprecated in docs/03-api.md — until clients migrate.
Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'abilities:links:create', 'throttle:api-links'])->group(function () {
        Route::post('/links', [LinkController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'abilities:links:read', 'throttle:api-read'])->group(function () {
        Route::get('/links/{code}', [LinkController::class, 'show']);
    });

    // Updates share the write throttle bucket with creation.
    Route::middleware(['auth:sanctum', 'abilities:links:update', 'throttle:api-links'])->group(function () {
        Route::patch('/links/{code}', [LinkController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'abilities:links:create', 'throttle:api-read'])->group(function () {
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/domain-groups', [DomainGroupController::class, 'index']);
    });
});
