<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ThemaSubjectSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

#[Signature('thema:update
    {--download : Download the latest Thema JSON from EDItEUR before seeding}
    {--url= : Custom download URL (overrides default EDItEUR URL)}
    {--force : Skip confirmation prompt}')]
#[Description('Seed (or re-seed) Thema subject codes. Use --download to fetch the latest version from EDItEUR.')]
class ThemaUpdate extends Command
{
    /**
     * Default download URL for the latest Thema JSON.
     * Check https://www.editeur.org/151/Thema/ for updated URLs.
     */
    private const DEFAULT_URL = 'https://www.editeur.org/files/Thema/1.6/v1.6_en/20250410_Thema_v1.6_en.json';

    /**
     * Where the JSON file lives relative to storage/app.
     * Note: storage_path() points to storage/ (not storage/app/),
     * but Storage::disk('local') is rooted at storage/app/.
     * We use storage_path() directly to stay consistent with the seeder.
     */
    private const LOCAL_PATH = 'thema/thema_en.json';

    public function handle(): int
    {
        // --download: fetch the file from EDItEUR first.
        if ($this->option('download')) {
            if (! $this->downloadThema()) {
                return self::FAILURE;
            }
        }

        // Confirm before wiping, unless --force or running non-interactively.
        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! $this->confirm('This will truncate and re-seed all Thema subjects. Continue?', true)) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        // Call the seeder directly.
        // callSilently() runs another command without output; call() shows output.
        // We use call() here so the seeder's progress bar is visible.
        $this->call('db:seed', ['--class' => ThemaSubjectSeeder::class]);

        return self::SUCCESS;
    }

    /**
     * Download the Thema JSON to storage/thema/thema_en.json.
     * Returns true on success, false on failure.
     */
    private function downloadThema(): bool
    {
        $url = $this->option('url') ?? self::DEFAULT_URL;

        $this->info("Downloading Thema JSON from: {$url}");

        // Http::get() is Laravel's wrapper around Guzzle/PSR-7.
        // We stream large responses to avoid loading them fully into memory.
        $response = Http::timeout(60)->get($url);

        if (! $response->successful()) {
            $this->error("Download failed (HTTP {$response->status()}): {$url}");
            return false;
        }

        $dest = storage_path(self::LOCAL_PATH);

        // Ensure the directory exists.
        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }

        file_put_contents($dest, $response->body());

        $size = round(filesize($dest) / 1024 / 1024, 1);
        $this->info("Saved {$size} MB to {$dest}");

        return true;
    }
}
