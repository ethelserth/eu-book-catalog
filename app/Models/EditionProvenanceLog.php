<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditionProvenanceLog extends Model
{
    use HasUuid;

    // Only created_at, no updated_at (logs are immutable)
    const UPDATED_AT = null;

    protected $table = 'edition_provenance_log';

    protected $fillable = [
        'edition_id',
        'provenance_id',
        'action',
        'previous_data',
    ];

    protected $casts = [
        'previous_data' => 'array',  // JSON to PHP array
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function provenance(): BelongsTo
    {
        return $this->belongsTo(Provenance::class);
    }
}