<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProviderType;
use App\Models\ProviderCredential;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('catalog:sync
    {--provider= : Only sync a specific provider (e.g. openlibrary)}
    {--full      : Force a full historical fetch even if the provider has synced before}
    {--dry-run   : Pass --dry-run through to each provider fetch command}
    {--force     : Run even if provider has auto_sync disabled}')]
#[Description('Run sync for all active providers. Auto-detects full vs incremental based on last run.')]
class CatalogSync extends Command
{
    public function handle(): int
    {
        $only = $this->option('provider');
        $forceFull = (bool) $this->option('full');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = ProviderCredential::where('is_active', true);

        if (! $force) {
            $query->where('auto_sync', true);
        }

        if ($only) {
            $query->where('provider', $only);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->warn(
                'No active providers with auto_sync enabled. '.
                'Go to Settings → Data Providers to configure them.'
            );

            return self::SUCCESS;
        }

        $this->info("catalog:sync — {$providers->count()} provider(s) queued.");

        $allOk = true;

        foreach ($providers as $provider) {
            $this->newLine();
            $this->info("── {$provider->label} [{$provider->provider->value}] ──");

            $command = $this->buildCommand($provider, $forceFull, $dryRun);

            if ($command === null) {
                $this->warn("  No fetch command registered for [{$provider->provider}]. Skipping.");

                continue;
            }

            $mode = $forceFull || $provider->last_ingestion_at === null ? 'FULL' : 'INCREMENTAL';
            $this->info("  Mode: {$mode}");
            $this->line("  Running: {$command}");

            try {
                $exitCode = $this->call(...$this->parseCommand($command));

                if ($exitCode === self::SUCCESS) {
                    if (! $dryRun) {
                        $provider->update(['last_ingestion_at' => now()]);
                    }
                    $this->info('  ✓ Done.');
                } else {
                    $this->error("  ✗ Exited with code {$exitCode}.");
                    $allOk = false;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception: {$e->getMessage()}");
                Log::error('catalog:sync provider failed', [
                    'provider' => $provider->provider->value,
                    'error' => $e->getMessage(),
                ]);
                $allOk = false;
            }
        }

        $this->newLine();

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Command building — decides full vs incremental per provider
    // -------------------------------------------------------------------------

    /**
     * Builds the artisan command string for a given provider.
     *
     * Decision logic:
     *   - If last_ingestion_at is null (never synced) OR --full is passed: full fetch
     *   - Otherwise: incremental from the day of last sync
     *
     * Each provider can store a 'full_sync_from' date in its settings column
     * to control how far back the initial full fetch goes. Defaults to 1 year ago.
     */
    private function buildCommand(ProviderCredential $provider, bool $forceFull, bool $dryRun): ?string
    {
        $isFirstRun = $provider->last_ingestion_at === null;
        $doFull = $forceFull || $isFirstRun;
        $dry = $dryRun ? ' --dry-run' : '';

        return match ($provider->provider) {

            ProviderType::OpenLibrary => $doFull
                ? 'openlibrary:fetch --full --since='.$this->fullSyncFrom($provider).$dry
                : 'openlibrary:fetch --sync --date='.$provider->last_ingestion_at->toDateString().$dry,

            ProviderType::Biblionet => $doFull
                ? 'biblionet:fetch --full'.$dry
                : 'biblionet:fetch --since='.$provider->last_ingestion_at->toDateString().$dry,
        };
    }

    /**
     * Returns the start date for a full historical fetch.
     * Reads from the provider's settings.full_sync_from, falls back to 1 year ago.
     */
    private function fullSyncFrom(ProviderCredential $provider): string
    {
        return $provider->setting('full_sync_from')
            ?? now()->subYear()->toDateString();
    }

    /**
     * Splits a command string into [command, options array] for $this->call().
     *
     * $this->call() takes the command name and an array of arguments/options,
     * not a raw string — so we parse it here.
     *
     * e.g. "openlibrary:fetch --full --since=2025-01-01 --dry-run"
     *   → ['openlibrary:fetch', ['--full' => true, '--since' => '2025-01-01', '--dry-run' => true]]
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function parseCommand(string $commandString): array
    {
        $parts = explode(' ', trim($commandString));
        $name = array_shift($parts);
        $options = [];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $options[$key] = $value;
            } else {
                $options[$part] = true;
            }
        }

        return [$name, $options];
    }
}
