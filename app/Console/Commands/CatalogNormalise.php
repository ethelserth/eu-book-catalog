<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessRawIngestionJob;
use App\Mappers\MapperRegistry;
use App\Models\Provenance;
use App\Models\RawIngestionRecord;
use App\Services\CatalogWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Throwable;

#[Signature('catalog:normalise
    {--provider=   : Restrict to one source_system (openlibrary, biblionet)}
    {--record-type= : Restrict to one record_type (edition, work, author, title)}
    {--status=pending : Status filter (pending|failed). Default: pending}
    {--limit=        : Stop after N records}
    {--queue         : Dispatch as background jobs instead of running inline}
    {--retry-failed  : Re-process records previously marked failed}
    {--dry-run       : Run mappers but do not write to the catalog}')]
#[Description('Normalise raw_ingestion_records into the FRBR catalog (works/expressions/editions/authors).')]
class CatalogNormalise extends Command
{
    public function handle(MapperRegistry $registry, CatalogWriter $writer): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $status = (string) $this->option('status');
        $dryRun = (bool) $this->option('dry-run');
        $queue = (bool) $this->option('queue');
        $retryFailed = (bool) $this->option('retry-failed');

        $query = RawIngestionRecord::query()
            ->where('status', $retryFailed ? 'failed' : $status);

        if ($only = $this->option('provider')) {
            $query->where('source_system', $only);

            if (! $registry->has((string) $only)) {
                $this->error("No mapper registered for source_system '{$only}'.");
                $this->line('Registered: '.implode(', ', $registry->registeredSourceSystems()));

                return self::FAILURE;
            }
        }

        if ($recordType = $this->option('record-type')) {
            $query->where('record_type', $recordType);
        }

        $this->processInPasses($query, $registry, $writer, $limit, $dryRun, $queue);

        return self::SUCCESS;
    }

    /**
     * Why multiple passes? OpenLibrary edition records carry only author OLID refs;
     * the names are on author records. Processing authors first means edition
     * mapping can resolve the OLID via lookupCompanion() and get a real name.
     * Same logic applies to works→editions.
     */
    private function processInPasses(
        Builder $query,
        MapperRegistry $registry,
        CatalogWriter $writer,
        ?int $limit,
        bool $dryRun,
        bool $queue,
    ): void {
        $remaining = $limit;
        $totalDone = 0;

        foreach (['author', 'work', 'edition', 'title'] as $type) {
            if ($remaining !== null && $remaining <= 0) {
                break;
            }

            $passQuery = (clone $query)->where('record_type', $type);
            $count = (clone $passQuery)->count();

            if ($count === 0) {
                continue;
            }

            $passLimit = $remaining !== null ? min($remaining, $count) : $count;

            $this->info("Pass: {$type} ({$passLimit} of {$count})");
            $done = $this->runPass($passQuery, $registry, $writer, $passLimit, $dryRun, $queue);
            $totalDone += $done;

            if ($remaining !== null) {
                $remaining -= $done;
            }
        }

        $this->newLine();
        $this->info("Done. Processed: {$totalDone}");
    }

    private function runPass(
        Builder $passQuery,
        MapperRegistry $registry,
        CatalogWriter $writer,
        int $passLimit,
        bool $dryRun,
        bool $queue,
    ): int {
        $batchProvenance = $dryRun ? null : $this->openProvenance();
        $processed = 0;
        $stop = false;

        $bar = $this->output->createProgressBar($passLimit);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('starting…');
        $bar->start();

        // chunkById() iterates in batches without loading everything into memory.
        // We enforce $passLimit inside the callback (chunkById ignores limit()).
        // Returning false breaks out of chunk iteration early.
        $passQuery->orderBy('id')->chunkById(100, function ($chunk) use (
            $registry, $writer, $batchProvenance, $dryRun, $queue, $passLimit, &$processed, &$stop, $bar,
        ) {
            foreach ($chunk as $record) {
                if ($processed >= $passLimit) {
                    $stop = true;

                    return false;
                }

                $bar->setMessage("{$record->source_system}/{$record->source_record_id}");

                try {
                    if ($queue && $batchProvenance) {
                        Bus::dispatch(new ProcessRawIngestionJob($record->id, $batchProvenance->id));
                    } else {
                        $this->processInline($record, $registry, $writer, $batchProvenance, $dryRun);
                    }
                    $processed++;
                    $bar->advance();
                } catch (Throwable $e) {
                    $bar->clear();
                    $this->error("  ✗ {$record->source_record_id}: {$e->getMessage()}");
                    $bar->display();
                    $bar->advance();
                }
            }

            return $stop ? false : null;
        });

        $bar->finish();
        $this->newLine();

        if (! $dryRun && $batchProvenance) {
            $this->closeProvenance($batchProvenance, $processed);
        }

        return $processed;
    }

    private function processInline(
        RawIngestionRecord $record,
        MapperRegistry $registry,
        CatalogWriter $writer,
        ?Provenance $provenance,
        bool $dryRun,
    ): void {
        $record->markProcessing();

        $mapped = $registry->for($record->source_system)->map($record);

        if ($mapped === null) {
            $record->markFailed('Mapper returned null (unsupported record_type or missing required fields)');

            return;
        }

        if ($dryRun) {
            $record->update(['status' => 'pending', 'processed_at' => null]);

            return;
        }

        $writer->write($mapped, $provenance, $record);
    }

    private function openProvenance(): Provenance
    {
        return Provenance::create([
            'source_system' => 'normaliser',
            'batch_id' => 'normalise-'.now()->format('Y-m-d-His'),
            'ingestion_started_at' => now(),
        ]);
    }

    private function closeProvenance(Provenance $provenance, int $processed): void
    {
        $provenance->update([
            'ingestion_completed_at' => now(),
            'records_processed' => $processed,
        ]);
    }
}
