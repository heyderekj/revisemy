<?php

use App\Console\Commands\ReviseMyCheck;
use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
 * Nightly housekeeping. Retention was only ever checked when a review was
 * read, so expired reviews and their screenshots stayed in storage for good.
 */
Schedule::command('model:prune', ['--model' => [Review::class]])->dailyAt('03:10')->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->dailyAt('03:20')->onOneServer();
Schedule::command('passport:purge')->dailyAt('03:30')->onOneServer();
Schedule::command('revisemy:prune-oauth-clients')->dailyAt('03:40')->onOneServer();

// Connect the way Claude does, every hour, so a broken sign-in is reported
// before someone hits it. Needs a try token to connect with.
Schedule::command('revisemy:probe-connect', ['--report'])
    ->hourlyAt(17)
    ->when(fn () => filled(config('revisemy.oauth.probe_token')))
    ->withoutOverlapping()
    ->onOneServer();

// So revisemy:check can tell a scheduler that isn't running.
Schedule::call(fn () => Cache::put(ReviseMyCheck::HEARTBEAT, now()->toIso8601String(), now()->addHour()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
