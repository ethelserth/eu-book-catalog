<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewQueue extends Model
{
    use HasUuid;

    const UPDATED_AT = null;  // Only created_at and resolved_at

    protected $table = 'review_queue';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'issue_type',
        'details',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'details' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * The user who resolved this issue.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the related entity (Author, Work, Edition, or Publisher).
     * 
     * Note: This is a manual polymorphic relation since entity_type
     * stores simple strings like 'author', not full class names.
     */
    public function entity(): ?Model
    {
        $class = match ($this->entity_type) {
            'author' => Author::class,
            'work' => Work::class,
            'edition' => Edition::class,
            'publisher' => Publisher::class,
            default => null,
        };

        if (!$class) {
            return null;
        }

        return $class::find($this->entity_id);
    }
}