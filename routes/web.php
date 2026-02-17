<?php

use App\Http\Controllers\ResolveLink;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{code}', ResolveLink::class);
