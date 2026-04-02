<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Clients\Contracts\OpenLibraryClientInterface;
use App\Models\Provenance;
use App\Models\RawIngestionRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('openlibrary:fetch
    {--isbn=   : Fetch a single edition by ISBN}
    {--work=   : Fetch a work and all its editions by OpenLibrary ID (e.g. OL45804W)}
    {--search= : Fetch editions matching a search query}
    {--full    : Full historical fetch — walks day-by-day from --since to yesterday}
    {--sync    : Incremental — fetch changes for a single day (--date, defaults to yesterday)}
    {--since=  : Start date for --full mode (YYYY-MM-DD, defaults to 1 year ago)}
    {--date=   : Target date for --sync mode (YYYY-MM-DD, defaults to yesterday)}
    {--limit=  : Stop after N records (useful for testing)}
    {--dry-run : Fetch from API but do not write to the database}')]
#[Description('Fetch records from the Open Library API and stage them as raw ingestion records.')]
class OpenLibraryFetch extends Command
{
    public function __construct(private readonly OpenLibraryClientInterface $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written to the database.');
        }

        // Route to the right strategy based on the flag provided.
        if ($this->option('isbn')) {
            return $this->runBatch('isbn:'.$this->option('isbn'), $this->fetchByIsbn($this->option('isbn')), $dryRun, $limit);

        } elseif ($this->option('work')) {
            return $this->runBatch('work:'.$this->option('work'), $this->fetchWork($this->option('work')), $dryRun, $limit);

        } elseif ($this->option('search')) {
            return $this->runBatch('search', $this->fetchSearch($this->option('search'), $limit), $dryRun, $limit);

        } elseif ($this->option('full')) {
            // Walk from --since to yesterday, one day at a time.
            $since = $this->option('since')
                ? Carbon::parse($this->option('since'))
                : Carbon::now()->subYear()->startOfDay();

            return $this->runFull($since, $dryRun, $limit);

        } elseif ($this->option('sync')) {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))
                : Carbon::yesterday();

            $this->info("Fetching changes for {$date->toDateString()}...");
            $records = $this->fetchChangesForDay($date, $limit);
            $this->info('  Found '.count($records).' editions.');

            return $this->runBatch(
                'sync:'.$date->toDateString(),
                $records,
                $dryRun,
                $limit,
            );

        } else {
            $this->error('Specify one of: --isbn, --work, --search, --full, --sync');

            return self::FAILURE;
        }
    }

    // -------------------------------------------------------------------------
    // Full historical fetch — walks day by day
    // -------------------------------------------------------------------------

    /**
     * Iterates every day from $since to yesterday.
     * For each day, fetches book changes and stages them.
     *
     * Why day-by-day instead of one big request?
     * OpenLibrary's RecentChanges API is scoped by date and has a max offset
     * of 10,000 per day. Walking day-by-day lets us page through history
     * in manageable chunks and resume from any point if interrupted.
     */
    private function runFull(Carbon $since, bool $dryRun, ?int $limit): int
    {
        $until = Carbon::yesterday();

        if ($since->isAfter($until)) {
            $this->warn("--since date ({$since->toDateString()}) is after yesterday. Nothing to fetch.");

            return self::SUCCESS;
        }

        // CarbonPeriod generates a date range, day by day — no array in memory.
        $period = CarbonPeriod::create($since, '1 day', $until);
        $totalDays = $since->diffInDays($until) + 1;

        $this->info("Full fetch: {$since->toDateString()} → {$until->toDateString()} ({$totalDays} days)");

        $bar = $this->output->createProgressBar((int) $totalDays);
        $bar->setFormat(' %current%/%max% days [%bar%] %percent:3s%% — %message%');
        // setMessage() must be called BEFORE start() when using a custom format
        // that includes %message%, otherwise Symfony renders the literal placeholder.
        $bar->setMessage('Starting...');
        $bar->start();

        $grandTotal = 0;
        $allOk = true;

        foreach ($period as $day) {
            $bar->setMessage($day->toDateString());
            $bar->display();

            $records = $this->fetchChangesForDay($day);

            if (! empty($records)) {
                $result = $this->runBatch(
                    'full:'.$day->toDateString(),
                    $records,
                    $dryRun,
                    $limit ? ($limit - $grandTotal) : null,
                    silent: true,
                );

                if ($result !== self::SUCCESS) {
                    $allOk = false;
                }

                $grandTotal += count($records);
            }

            $bar->advance();

            // If a limit was set and we've hit it, stop walking.
            if ($limit && $grandTotal >= $limit) {
                $this->newLine();
                $this->info("Reached --limit={$limit}, stopping.");
                break;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Full fetch complete. Total staged: {$grandTotal}");

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Single-batch runner — creates Provenance, stages records, closes batch
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function runBatch(
        string $label,
        array $records,
        bool $dryRun,
        ?int $limit,
        bool $silent = false,
    ): int {
        if (empty($records)) {
            if (! $silent) {
                $this->info('No records returned.');
            }

            return self::SUCCESS;
        }

        $batchId = 'openlibrary-'.now()->format('Y-m-d-His').'-'.str($label)->slug();
        $provenance = null;

        if (! $dryRun) {
            $provenance = Provenance::create([
                'source_system' => 'openlibrary',
                'batch_id' => $batchId,
                'ingestion_started_at' => now(),
            ]);
        }

        $fetched = $failed = 0;

        foreach ($records as $record) {
            if ($limit !== null && $fetched >= $limit) {
                break;
            }

            try {
                if (! $dryRun) {
                    $this->stageRecord($record, $provenance);
                }
                $fetched++;
            } catch (\Throwable $e) {
                $failed++;
                $id = $record['key'] ?? '?';
                Log::error('OpenLibraryFetch staging error', ['record' => $id, 'error' => $e->getMessage()]);
                if (! $silent) {
                    $this->error("Failed to stage {$id}: {$e->getMessage()}");
                }
            }
        }

        if (! $dryRun && $provenance) {
            $provenance->update([
                'ingestion_completed_at' => now(),
                'records_processed' => $fetched,
                'records_failed' => $failed,
            ]);
        }

        if (! $silent) {
            $this->info("Done. Staged: {$fetched} | Failed: {$failed}");
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Fetch strategies — each returns a plain array of raw records
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function fetchByIsbn(string $isbn): array
    {
        $this->info("Fetching ISBN: {$isbn}");
        $record = $this->client->fetchByIsbn($isbn);

        if (! $record) {
            $this->warn("ISBN {$isbn} not found in Open Library.");

            return [];
        }

        $record['_isbn_queried'] = $isbn;

        return [$record];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchWork(string $olid): array
    {
        $this->info("Fetching work: {$olid}");
        $work = $this->client->fetchWork($olid);
        $editions = $this->client->fetchWorkEditions($olid);
        $this->info('  Found '.count($editions).' editions.');

        return array_merge([$work], $editions);
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchSearch(string $query, ?int $limit): array
    {
        $this->info("Searching: {$query}");
        $perPage = min($limit ?? 100, 100);
        $result = $this->client->search($query, $perPage);
        $docs = $result['docs'] ?? [];
        $this->info("  Found {$result['numFound']} total, returning ".count($docs));

        return $docs;
    }

    /**
     * Fetch all book-change records for a single calendar day.
     *
     * OpenLibrary's RecentChanges API returns changesets (who changed what),
     * not book records. We extract the /books/ keys and fetch each edition via
     * /books/{olid}.json — NOT /works/{olid}.json, which is a different entity.
     *
     * Book OLIDs end in M (e.g. OL7353617M); work OLIDs end in W (e.g. OL45804W).
     * Calling fetchWork() on a book OLID hits the wrong endpoint and returns nothing.
     *
     * @param  int|null  $limit  Stop fetching after this many editions (applied before staging).
     * @return array<int, array<string, mixed>>
     */
    private function fetchChangesForDay(Carbon $day, ?int $limit = null): array
    {
        $changes = $this->client->fetchChanges($day, 500);
        $bookKeys = [];

        foreach ($changes as $change) {
            foreach ($change['changes'] ?? [] as $item) {
                $key = $item['key'] ?? '';
                if (str_starts_with($key, '/books/')) {
                    $bookKeys[] = $key;
                }
            }
        }

        $bookKeys = array_unique($bookKeys);

        // Slice before fetching so we don't make unnecessary HTTP requests.
        if ($limit !== null) {
            $bookKeys = array_slice($bookKeys, 0, $limit);
        }

        $total = count($bookKeys);
        $records = [];
        $i = 0;

        foreach ($bookKeys as $key) {
            $olid = basename($key, '.json');
            $i++;

            // Show per-record progress when -v (verbose) flag is passed.
            if ($this->output->isVerbose()) {
                $this->line("  [{$i}/{$total}] {$olid}");
            }

            try {
                $edition = $this->client->fetchEdition($olid);

                if (! empty($edition)) {
                    $records[] = $edition;
                }
            } catch (\Throwable $e) {
                Log::warning("OpenLibraryFetch: could not fetch edition {$olid}", ['error' => $e->getMessage()]);
            }
        }

        return $records;
    }

    // -------------------------------------------------------------------------
    // Staging
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $record */
    private function stageRecord(array $record, Provenance $provenance): void
    {
        $sourceId = ltrim($record['key'] ?? ($record['_isbn_queried'] ?? uniqid('ol-')), '/');

        RawIngestionRecord::updateOrCreate(
            ['source_system' => 'openlibrary', 'source_record_id' => $sourceId],
            [
                'payload' => $record,
                'status' => 'pending',
                'provenance_id' => $provenance->id,
                'fetched_at' => now(),
                'processed_at' => null,
                'error_message' => null,
            ]
        );
    }
}
