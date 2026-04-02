<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// -------------------------------------------------------------------------
// Unified provider sync — runs every night at 03:00.
//
// catalog:sync reads the provider_credentials table and runs the fetch
// command for every provider that has is_active=true and auto_sync=true.
// Enable/disable providers from Settings → Data Providers in the admin panel.
// No code changes needed to add a provider to automated sync.
//
// withoutOverlapping() — prevents a second run if the first is still going.
// runInBackground()    — scheduler doesn't block on this command.
// appendOutputTo()     — writes console output to a log file for monitoring.
// -------------------------------------------------------------------------
Schedule::command('catalog:sync')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/catalog-sync.log'));
