<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mappers\MapperRegistry;
use App\Models\Provenance;
use App\Models\RawIngestionRecord;
use App\Services\CatalogWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normalises a single raw_ingestion_records row into the FRBR catalog.
 *
 * Flow:
 *   raw record  →  MapperRegistry::for(source_system)  →  NormalisedRecord  →  CatalogWriter
 *
 * We intentionally do not retry on failure inside this job — the raw record's
 * own retry_count is bumped via markFailed(), and operators decide whether to
 * re-queue from the admin panel. Automatic retries would mask data-quality
 * issues that should be surfaced.
 */
class ProcessRawIngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private readonly string $rawRecordId,
        private readonly string $provenanceId,
    ) {}

    public function handle(MapperRegistry $registry, CatalogWriter $writer): void
    {
        $record = RawIngestionRecord::find($this->rawRecordId);
        if ($record === null) {
            Log::warning('ProcessRawIngestionJob: record vanished', ['id' => $this->rawRecordId]);

            return;
        }

        $provenance = Provenance::findOrFail($this->provenanceId);

        $record->markProcessing();

        try {
            if (! $registry->has($record->source_system)) {
                throw new \RuntimeException("No mapper for source_system '{$record->source_system}'");
            }

            $mapped = $registry->for($record->source_system)->map($record);

            if ($mapped === null) {
                $record->markFailed('Mapper returned null (unsupported record_type or missing required fields)');

                return;
            }

            $writer->write($mapped, $provenance, $record);
        } catch (Throwable $e) {
            $record->markFailed($e->getMessage());
            Log::error('ProcessRawIngestionJob failed', [
                'raw_record_id' => $this->rawRecordId,
                'source_system' => $record->source_system,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
