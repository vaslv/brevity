<?php

use App\Http\Controllers\Api\LinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/links', [LinkController::class, 'store']);
});
