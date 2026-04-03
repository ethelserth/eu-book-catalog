<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Provenance;
use App\Models\RawIngestionRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Ethelserth\Biblionet\BiblionetClient;
use Ethelserth\Biblionet\DTOs\Title;
use Ethelserth\Biblionet\Exceptions\BiblionetException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('biblionet:fetch
    {--full           : Full fetch — walks month-by-month, stages full title records}
    {--since=         : Incremental start date YYYY-MM-DD (default: yesterday). Full mode: start month YYYY-MM (default: 2000-01)}
    {--isbn=          : Fetch a single title by ISBN}
    {--id=            : Fetch a single title by Biblionet ID}
    {--limit=         : Stop after N titles staged}
    {--max-requests=  : Stop after N API requests (default: 950 — Biblionet allows 1 000/day)}
    {--dry-run        : Fetch from API but do not write to the database}')]
#[Description('Fetch titles from the Biblionet API and stage them as raw ingestion records.')]
class BiblionetFetch extends Command
{
    /**
     * Biblionet enforces a hard limit of 1 000 API requests per day.
     * We default to 950 to leave a safety buffer for other tooling.
     */
    private const DEFAULT_MAX_REQUESTS = 950;

    /** Running count of API calls made in this invocation. */
    private int $requestCount = 0;

    public function __construct(private readonly BiblionetClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $maxRequests = $this->option('max-requests') ? (int) $this->option('max-requests') : self::DEFAULT_MAX_REQUESTS;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written to the database.');
        }

        $this->info("Request budget: {$maxRequests}/day (Biblionet limit: 1 000/day)");

        try {
            if ($this->option('isbn')) {
                return $this->fetchByIsbn((string) $this->option('isbn'), $dryRun, $maxRequests);
            }

            if ($this->option('id')) {
                return $this->fetchById((int) $this->option('id'), $dryRun, $maxRequests);
            }

            if ($this->option('full')) {
                return $this->runFullFetch($dryRun, $limit, $maxRequests);
            }

            return $this->runIncrementalFetch($dryRun, $limit, $maxRequests);

        } catch (BiblionetException $e) {
            // Any unhandled API exception bubbles here — most likely bad credentials.
            $this->newLine();
            $this->error("Biblionet API error: {$e->getMessage()}");
            $this->error('Check your credentials in Settings → Data Providers.');
            Log::error('BiblionetFetch: fatal API error', ['error' => $e->getMessage()]);

            return self::FAILURE;
        } finally {
            $this->line("API requests used this run: {$this->requestCount}");
        }
    }

    // -------------------------------------------------------------------------
    // Single-title modes
    // -------------------------------------------------------------------------

    private function fetchByIsbn(string $isbn, bool $dryRun, int $maxRequests): int
    {
        $this->info("Fetching title by ISBN: {$isbn}");
        $this->guardRateLimit($maxRequests);

        $this->requestCount++;
        $title = $this->client->getTitleByIsbn($isbn);

        $this->info("Found: {$title->title} (ID: {$title->titlesId})");

        if (! $dryRun) {
            $provenance = $this->openProvenance("isbn:{$isbn}");
            $this->stageTitle($title, $provenance);
            $this->closeProvenance($provenance, staged: 1, failed: 0);
        }

        return self::SUCCESS;
    }

    private function fetchById(int $id, bool $dryRun, int $maxRequests): int
    {
        $this->info("Fetching title by ID: {$id}");
        $this->guardRateLimit($maxRequests);

        $this->requestCount++;
        $title = $this->client->getTitle($id);

        $this->info("Found: {$title->title}");

        if (! $dryRun) {
            $provenance = $this->openProvenance("id:{$id}");
            $this->stageTitle($title, $provenance);
            $this->closeProvenance($provenance, staged: 1, failed: 0);
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Incremental fetch — day-by-day via getTitlesByLastUpdate()
    //
    // Why day-by-day? The Biblionet API's lastupdate filter is scoped to a
    // single date — there is no "from X to Y" range. Walking one day at a
    // time lets us resume from any point if the job is interrupted.
    // Each day = 1 API request, so 950 days ≈ 2.6 years fits within the budget.
    // -------------------------------------------------------------------------

    private function runIncrementalFetch(bool $dryRun, ?int $limit, int $maxRequests): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : Carbon::yesterday();

        $until = Carbon::yesterday();

        if ($since->isAfter($until)) {
            $this->warn("--since ({$since->toDateString()}) is after yesterday — nothing to fetch.");

            return self::SUCCESS;
        }

        $this->info("Incremental fetch: {$since->toDateString()} → {$until->toDateString()}");

        $period = CarbonPeriod::create($since, '1 day', $until);
        $days = (int) $since->diffInDays($until) + 1;

        $bar = $this->output->createProgressBar($days);
        $bar->setFormat(' %current%/%max% days [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $total = 0;

        foreach ($period as $day) {
            $dateStr = $day->toDateString();
            $bar->setMessage($dateStr);

            if (! $this->hasRemainingBudget($maxRequests)) {
                $this->newLine();
                $this->warn("Daily request budget reached ({$this->requestCount}/{$maxRequests}). Stopping — resume with --since={$dateStr}");
                break;
            }

            // Any BiblionetException here (auth failure, server error) bubbles
            // to handle() which logs it, shows a clear message, and returns FAILURE.
            $this->requestCount++;
            $titles = $this->client->getTitlesByLastUpdate($dateStr);

            if (! empty($titles)) {
                $staged = $failed = 0;
                $provenance = $dryRun ? null : $this->openProvenance("incremental:{$dateStr}");

                foreach ($titles as $title) {
                    if ($limit !== null && $total >= $limit) {
                        break 2;
                    }

                    try {
                        if (! $dryRun) {
                            $this->stageTitle($title, $provenance);
                        }
                        $staged++;
                        $total++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('BiblionetFetch: staging error', [
                            'id' => $title->titlesId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (! $dryRun && $provenance) {
                    $this->closeProvenance($provenance, $staged, $failed);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Staged: {$total}");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Full fetch — month-by-month via getMonthTitles()
    //
    // The API returns full Title records (same structure as get_title).
    // Each page of 50 titles = 1 API request.
    // -------------------------------------------------------------------------

    private function runFullFetch(bool $dryRun, ?int $limit, int $maxRequests): int
    {
        $sinceOption = $this->option('since') ?? '2000-01';
        $parts = explode('-', $sinceOption);
        $startYear = (int) $parts[0];
        $startMonth = isset($parts[1]) ? (int) $parts[1] : 1;

        $now = Carbon::now();
        $this->info("Full fetch: {$sinceOption} → {$now->format('Y-m')} (title summaries — enrich in Phase 5)");

        $total = 0;
        $year = $startYear;
        $month = $startMonth;

        while ($year < $now->year || ($year === $now->year && $month <= $now->month)) {
            $label = sprintf('%04d-%02d', $year, $month);
            $this->line("  → {$label}");

            $page = 1;
            $provenance = null;
            $monthStaged = 0;
            $monthFailed = 0;

            do {
                if (! $this->hasRemainingBudget($maxRequests)) {
                    $this->warn("Daily request budget reached ({$this->requestCount}/{$maxRequests}). Stopping — resume with --since={$label}");
                    goto done;
                }

                // 500 means end-of-data for this month (API bug — returns 500
                // instead of empty array when the page is out of range).
                // Any other exception bubbles to handle() and stops the command.
                $this->requestCount++;

                try {
                    $summaries = $this->client->getMonthTitles($year, $month, $page, perPage: 50);
                } catch (BiblionetException $e) {
                    if ($e->getCode() === 500) {
                        Log::info("BiblionetFetch: 500 on {$label} page {$page} — treating as end of month data.");
                        break;
                    }

                    throw $e;
                }

                if (empty($summaries)) {
                    break;
                }

                if (! $dryRun && $provenance === null) {
                    $provenance = $this->openProvenance("full:{$label}");
                }

                foreach ($summaries as $title) {
                    if ($limit !== null && $total >= $limit) {
                        goto done;
                    }

                    try {
                        if (! $dryRun) {
                            $this->stageTitle($title, $provenance);
                        }
                        $monthStaged++;
                        $total++;
                    } catch (\Throwable $e) {
                        $monthFailed++;
                        Log::error('BiblionetFetch: staging error', [
                            'id' => $title->titlesId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $page++;
            } while (count($summaries) >= 50);

            if (! $dryRun && $provenance) {
                $this->closeProvenance($provenance, $monthStaged, $monthFailed);
            }

            $month++;

            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        done:
        $this->info("Full fetch complete. Staged: {$total} titles.");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Rate limit helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true if we can still make at least one more request.
     */
    private function hasRemainingBudget(int $maxRequests): bool
    {
        return $this->requestCount < $maxRequests;
    }

    /**
     * Hard stop for single-title modes where there is no loop to check first.
     */
    private function guardRateLimit(int $maxRequests): void
    {
        if (! $this->hasRemainingBudget($maxRequests)) {
            throw new \RuntimeException("Daily request budget of {$maxRequests} already exhausted.");
        }
    }

    // -------------------------------------------------------------------------
    // Provenance helpers
    // -------------------------------------------------------------------------

    private function openProvenance(string $label): Provenance
    {
        return Provenance::create([
            'source_system' => 'biblionet',
            'batch_id' => 'biblionet-'.now()->format('Y-m-d-His').'-'.str($label)->slug(),
            'ingestion_started_at' => now(),
        ]);
    }

    private function closeProvenance(Provenance $provenance, int $staged, int $failed): void
    {
        $provenance->update([
            'ingestion_completed_at' => now(),
            'records_processed' => $staged,
            'records_failed' => $failed,
        ]);
    }

    // -------------------------------------------------------------------------
    // Staging helpers
    //
    // DTOs are readonly classes — json_encode() serialises all public properties,
    // then json_decode() with associative=true gives us a plain array for the
    // payload column (jsonb in PostgreSQL).
    // -------------------------------------------------------------------------

    private function stageTitle(Title $title, Provenance $provenance): void
    {
        RawIngestionRecord::updateOrCreate(
            [
                'source_system' => 'biblionet',
                'source_record_id' => (string) $title->titlesId,
            ],
            [
                'record_type' => 'title',
                'payload' => json_decode(json_encode($title), associative: true),
                'status' => 'pending',
                'provenance_id' => $provenance->id,
                'fetched_at' => now(),
                'processed_at' => null,
                'error_message' => null,
            ]
        );
    }
}
