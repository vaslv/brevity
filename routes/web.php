<?php

use App\Http\Controllers\ResolveLink;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return [
        'data' => [
            'request' => \Illuminate\Support\Str::ulid(),
            'success' => true,
        ],
    ];
});

Route::get('/{code}', ResolveLink::class);
