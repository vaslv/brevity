<?php

use App\Http\Controllers\ResolveLink;
use Illuminate\Support\Facades\Route;

Route::get('/{code}', ResolveLink::class)->middleware('throttle:link-resolve');
