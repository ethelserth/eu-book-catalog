<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Provenance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Signature('openlibrary:import-dump
    {--type=all      : Record type to import: editions, works, authors, all (default: all)}
    {--file=         : Path to a local .txt.gz file (skips download)}
    {--limit=        : Stop after N records (useful for testing)}
    {--dry-run       : Parse and count but do not write to the database}
    {--chunk=500     : Records per database upsert batch (default: 500)}')]
#[Description('Stream-import an OpenLibrary bulk data dump into raw_ingestion_records. Run once for the initial load; use catalog:sync for ongoing deltas.')]
class OpenLibraryImportDump extends Command
{
    /**
     * OpenLibrary publishes monthly full dumps at these URLs.
     * "_latest" always resolves to the most recent file.
     * 'all' refers to the combined dump containing every record type.
     *
     * @var array<string, string>
     */
    private const DUMP_URLS = [
        'editions' => 'https://openlibrary.org/data/ol_dump_editions_latest.txt.gz',
        'works' => 'https://openlibrary.org/data/ol_dump_works_latest.txt.gz',
        'authors' => 'https://openlibrary.org/data/ol_dump_authors_latest.txt.gz',
        'all' => 'https://openlibrary.org/data/ol_dump_latest.txt.gz',
    ];

    /**
     * Record types we want to import. All others (redirect, delete, etc.) are skipped.
     *
     * @var array<int, string>
     */
    private const IMPORTABLE_TYPES = ['edition', 'work', 'author'];

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) ($this->option('chunk') ?? 500));

        if (! array_key_exists($type, self::DUMP_URLS)) {
            $this->error("Unknown type '{$type}'. Valid values: ".implode(', ', array_keys(self::DUMP_URLS)));

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written to the database.');
        }

        $source = $this->option('file') ?? self::DUMP_URLS[$type];
        $isUrl = str_starts_with($source, 'http');

        $this->info("OpenLibrary dump import — type: {$type}");
        $this->info($isUrl ? "Source URL: {$source}" : "Local file: {$source}");
        $this->newLine();

        // Two different streaming strategies:
        //
        // Remote URL → popen("curl | gzip -d"):
        //   compress.zlib:// silently returns an empty stream for HTTPS URLs because
        //   it does not follow HTTP redirects (OpenLibrary _latest URLs redirect to a
        //   dated file). curl handles SSL, redirects, and retries transparently.
        //   popen() returns a regular PHP stream resource compatible with fgets/feof.
        //
        // Local file → compress.zlib:// fopen:
        //   Works correctly for local .gz files without any external tools.
        if ($isUrl) {
            $handle = popen('curl -sL --retry 3 '.escapeshellarg($source).' | gzip -d', 'r');
        } else {
            $streamPath = str_ends_with($source, '.gz') ? "compress.zlib://{$source}" : $source;
            $handle = @fopen($streamPath, 'r');
        }

        if ($handle === false) {
            $this->error("Could not open stream: {$source}");

            return self::FAILURE;
        }

        $provenance = null;

        if (! $dryRun) {
            $provenance = Provenance::create([
                'source_system' => 'openlibrary',
                'batch_id' => 'openlibrary-dump-'.$type.'-'.now()->format('Y-m-d-His'),
                'ingestion_started_at' => now(),
            ]);
        }

        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $batch = [];

        // Cache now() outside the loop — calling it millions of times would be slow.
        $fetchedAt = now()->toDateTimeString();

        $this->line('Streaming dump file (this takes several minutes for full dumps)...');
        $this->line('Progress is reported every 10,000 records.');
        $this->newLine();

        while (! feof($handle)) {
            $line = fgets($handle);

            if ($line === false || trim($line) === '') {
                continue;
            }

            // Dump format (tab-separated, 5 columns):
            //   type  |  key  |  revision  |  last_modified  |  json_payload
            // Example:
            //   /type/edition  /books/OL7353617M  5  2024-11-01T10:22:00  {"key":"/books/OL7353617M",...}
            $parts = explode("\t", $line, 5);

            if (count($parts) < 5) {
                $skipped++;

                continue;
            }

            [$typeRaw, $key, , , $jsonRaw] = $parts;

            // Derive provider-vocabulary record_type: "/type/edition" → "edition"
            $recordType = ltrim(str_replace('/type/', '', trim($typeRaw)), '/');

            // Skip redirects, deletes, and any other non-importable types.
            if (! in_array($recordType, self::IMPORTABLE_TYPES, strict: true)) {
                $skipped++;

                continue;
            }

            // When a per-type file is used (e.g. --type=editions), filter to only
            // that type so a mis-labelled combined file doesn't produce wrong data.
            if ($type !== 'all' && $recordType !== rtrim($type, 's')) {
                $skipped++;

                continue;
            }

            $payload = json_decode(trim($jsonRaw), true);

            if (! is_array($payload)) {
                $failed++;
                Log::warning('OpenLibraryImportDump: invalid JSON', ['key' => $key]);

                continue;
            }

            // Strip leading slash: "/books/OL7353617M" → "books/OL7353617M"
            $sourceId = ltrim($key, '/');

            if (! $dryRun) {
                $batch[] = [
                    // UUID must be provided explicitly when bypassing Eloquent (HasUuid trait won't fire).
                    // For upserted (existing) rows the id is preserved; this uuid is only used for inserts.
                    'id' => (string) Str::uuid(),
                    'source_system' => 'openlibrary',
                    'source_record_id' => $sourceId,
                    'record_type' => $recordType,
                    'payload' => json_encode($payload),
                    'status' => 'pending',
                    'provenance_id' => $provenance?->id,
                    'retry_count' => 0,
                    'fetched_at' => $fetchedAt,
                    'processed_at' => null,
                    'error_message' => null,
                    'created_at' => $fetchedAt,
                    'updated_at' => $fetchedAt,
                ];

                if (count($batch) >= $chunk) {
                    $this->flushBatch($batch);
                    $batch = [];
                }
            }

            $processed++;

            if ($processed % 10_000 === 0) {
                $mem = round(memory_get_usage(true) / 1024 / 1024, 1);
                $this->line("  {$processed} records | mem: {$mem}MB | failed: {$failed}");
            }

            if ($limit !== null && $processed >= $limit) {
                $this->info("Reached --limit={$limit}, stopping.");
                break;
            }
        }

        // popen handles must be closed with pclose() to reap the child process;
        // fopen handles use fclose().
        if ($isUrl) {
            pclose($handle);
        } else {
            fclose($handle);
        }

        // Flush any remaining records that didn't fill a complete chunk.
        if (! $dryRun && ! empty($batch)) {
            $this->flushBatch($batch);
        }

        if (! $dryRun && $provenance) {
            $provenance->update([
                'ingestion_completed_at' => now(),
                'records_processed' => $processed,
                'records_failed' => $failed,
            ]);
        }

        $this->newLine();
        $this->info('Import complete.');
        $this->table(
            ['Type', 'Processed', 'Failed', 'Skipped (bad lines)'],
            [[$type, $processed, $failed, $skipped]],
        );

        return self::SUCCESS;
    }

    /**
     * Batch-upsert into raw_ingestion_records.
     *
     * upsert() maps to PostgreSQL's INSERT ... ON CONFLICT DO UPDATE.
     * The unique key is (source_system, source_record_id).
     * On conflict: update the payload and status (re-queue for normalisation).
     * The 'id' column is NOT in the update list — existing records keep their UUID.
     *
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flushBatch(array $batch): void
    {
        DB::table('raw_ingestion_records')->upsert(
            $batch,
            ['source_system', 'source_record_id'],
            ['record_type', 'payload', 'status', 'provenance_id', 'fetched_at', 'processed_at', 'error_message', 'updated_at'],
        );
    }
}
