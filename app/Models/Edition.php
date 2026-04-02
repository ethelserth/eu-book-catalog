<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    use HasUuid;

    protected $fillable = [
        'expression_id',
        'publisher_id',
        'isbn13',
        'isbn10',
        'publication_date',
        'publication_year',
        'format',
        'pages',
        'cover_url',
        'source_system',
        'source_record_id',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'publication_year' => 'integer',
        'pages' => 'integer',
    ];

    public function expression(): BelongsTo
    {
        return $this->belongsTo(Expression::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * Provenance log entries for this edition.
     */
    public function provenanceLog(): HasMany
    {
        return $this->hasMany(EditionProvenanceLog::class);
    }

    /**
     * Convenience: get the work through expression.
     */
    public function work(): BelongsTo
    {
        return $this->expression->work();
    }
}