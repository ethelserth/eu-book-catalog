<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Clients\Contracts\BiblionetClientInterface;
use App\Clients\Exceptions\BiblionetApiException;
use App\Clients\Exceptions\BiblionetAuthException;
use App\Clients\Exceptions\BiblionetRateLimitException;
use App\Models\Provenance;
use App\Models\RawIngestionRecord;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('biblionet:fetch
    {--full : Fetch all books (ignores --since)}
    {--since= : Fetch books modified on or after this date (YYYY-MM-DD). Defaults to yesterday.}
    {--limit= : Stop after fetching this many books (useful for testing)}
    {--dry-run : Fetch from API but do not write to the database}')]
#[Description('Fetch books from the BIBLIONET API and stage them as raw ingestion records.')]
class BiblionetFetch extends Command
{
    public function __construct(private readonly BiblionetClientInterface $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit  = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written to the database.');
        }

        // ------------------------------------------------------------------
        // Determine fetch mode
        // ------------------------------------------------------------------

        if ($this->option('full')) {
            $this->info('Mode: full fetch (all books)');
            $generator = $this->fullFetch();
        } else {
            $since = $this->option('since')
                ? Carbon::parse($this->option('since'))
                : Carbon::yesterday();

            $this->info("Mode: incremental fetch since {$since->toDateString()}");
            $generator = $this->incrementalFetch($since);
        }

        // ------------------------------------------------------------------
        // Create a Provenance record to track this batch.
        //
        // Provenance is our audit trail — every set of records fetched
        // together shares one Provenance, so we can always answer:
        // "where did this edition come from and when?"
        // ------------------------------------------------------------------

        $batchId    = 'biblionet-' . now()->format('Y-m-d-His');
        $provenance = null;

        if (! $dryRun) {
            $provenance = Provenance::create([
                'source_system'       => 'biblionet',
                'batch_id'            => $batchId,
                'ingestion_started_at' => now(),
            ]);

            $this->info("Provenance batch: {$batchId}");
        }

        // ------------------------------------------------------------------
        // Iterate the generator, staging each record.
        // ------------------------------------------------------------------

        $fetched = $created = $updated = $failed = 0;

        foreach ($generator as $book) {
            if ($limit && $fetched >= $limit) {
                $this->info("Reached --limit={$limit}, stopping.");
                break;
            }

            try {
                if (! $dryRun) {
                    $this->stageRecord($book, $provenance);
                }

                $fetched++;

                if ($fetched % 50 === 0) {
                    $this->info("  Fetched {$fetched} records...");
                }

            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed to stage record {$book['id'] ?? '?'}: {$e->getMessage()}");
                Log::error('BiblionetFetch staging error', [
                    'record' => $book['id'] ?? null,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // ------------------------------------------------------------------
        // Update provenance stats on completion.
        // ------------------------------------------------------------------

        if (! $dryRun && $provenance) {
            $provenance->update([
                'ingestion_completed_at' => now(),
                'records_processed'      => $fetched,
                'records_failed'         => $failed,
            ]);
        }

        $this->info("Done. Fetched: {$fetched} | Failed: {$failed}");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Fetch strategies — both return iterables of raw book arrays
    // -------------------------------------------------------------------------

    /**
     * Full fetch: page through /books from the beginning.
     * Returns a Generator so we never hold all books in memory.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function fullFetch(): \Generator
    {
        $page = 1;

        do {
            $this->line("  → fetching page {$page}...");

            try {
                $books = $this->client->fetchBooks($page, 100);
            } catch (BiblionetRateLimitException $e) {
                $this->warn("Rate limited. Sleeping {$e->retryAfter}s...");
                sleep($e->retryAfter);
                // Retry the same page.
                $books = $this->client->fetchBooks($page, 100);
            }

            foreach ($books as $book) {
                yield $book;
            }

            $page++;

        } while (count($books) === 100); // assume full page means more pages exist
    }

    /**
     * Incremental fetch: books modified since $since.
     * Delegates pagination to the client's Generator-based method.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function incrementalFetch(Carbon $since): \Generator
    {
        yield from $this->client->fetchBooksSince($since);
    }

    // -------------------------------------------------------------------------
    // Staging
    // -------------------------------------------------------------------------

    /**
     * Upsert a raw book record into the staging table.
     *
     * updateOrCreate() is Laravel's "insert or update" — it looks up the
     * record by the first array (the unique key), and if found updates it
     * with the second array; if not found, merges both arrays and inserts.
     *
     * @param  array<string, mixed>  $book
     */
    private function stageRecord(array $book, Provenance $provenance): void
    {
        RawIngestionRecord::updateOrCreate(
            [
                'source_system'    => 'biblionet',
                'source_record_id' => (string) $book['id'],
            ],
            [
                'payload'      => $book,
                'status'       => 'pending',
                'provenance_id' => $provenance->id,
                'fetched_at'   => now(),
                // Reset processing state so the pipeline will re-process it.
                'processed_at' => null,
                'error_message' => null,
            ]
        );
    }
}
