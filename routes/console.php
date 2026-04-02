<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// -------------------------------------------------------------------------
// BIBLIONET incremental sync — runs every night at 03:00.
//
// withoutOverlapping() prevents a second instance starting if the first
// is still running (e.g. large batch). Laravel uses a cache lock for this.
//
// runInBackground() means the scheduler doesn't wait for this job to finish
// before moving to the next scheduled task.
//
// appendOutputTo() tails output to a log file for ops monitoring.
// -------------------------------------------------------------------------
Schedule::command('biblionet:fetch --since=yesterday')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/biblionet-sync.log'));
