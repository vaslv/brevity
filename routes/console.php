<?php

use App\Console\Commands\RedispatchStaleCallbacks;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Console\SnapshotCommand;

Schedule::call(function () {
    @touch('/tmp/healthy');
})->everyTenSeconds();

Schedule::command(SnapshotCommand::class)->everyFiveMinutes();

Schedule::command(RedispatchStaleCallbacks::class)->everyThirtyMinutes();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
