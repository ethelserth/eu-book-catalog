<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provenance extends Model
{
    use HasUuid;

    protected $table = 'provenance';  // Laravel would guess 'provenances'

    protected $fillable = [
        'source_system',
        'source_url',
        'batch_id',
        'ingestion_started_at',
        'ingestion_completed_at',
        'records_processed',
        'records_created',
        'records_updated',
        'records_failed',
        'error_log',
    ];

    protected $casts = [
        'ingestion_started_at' => 'datetime',
        'ingestion_completed_at' => 'datetime',
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
    ];

    /**
     * All edition log entries from this batch.
     */
    public function editionLogs(): HasMany
    {
        return $this->hasMany(EditionProvenanceLog::class);
    }

    /**
     * Raw records from this batch.
     */
    public function rawRecords(): HasMany
    {
        return $this->hasMany(RawIngestionRecord::class);
    }
}