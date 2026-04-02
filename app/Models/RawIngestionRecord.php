<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawIngestionRecord extends Model
{
    use HasUuid;

    protected $fillable = [
        'source_system',
        'source_record_id',
        'payload',
        'status',
        'provenance_id',
        'edition_id',
        'error_message',
        'retry_count',
        'fetched_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'fetched_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function provenance(): BelongsTo
    {
        return $this->belongsTo(Provenance::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * Mark as processing.
     */
    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark as completed with linked edition.
     */
    public function markCompleted(Edition $edition): void
    {
        $this->update([
            'status' => 'completed',
            'edition_id' => $edition->id,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as failed with error message.
     */
    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}