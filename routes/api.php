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
