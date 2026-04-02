<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasUuid;

    protected $fillable = [
        'display_name',
        'sort_name',
        'birth_year',
        'death_year',
        'nationality',
        'viaf_id',
        'isni',
        'wikidata_id',
        'authority_confidence',
        'needs_review',
    ];

    protected $casts = [
        'birth_year' => 'integer',
        'death_year' => 'integer',
        'authority_confidence' => 'decimal:2',
        'needs_review' => 'boolean',
    ];

    /**
     * All name variants for this author.
     */
    public function nameVariants(): HasMany
    {
        return $this->hasMany(AuthorNameVariant::class);
    }

    /**
     * Works where this author is a creator.
     */
    public function works(): BelongsToMany
    {
        return $this->belongsToMany(Work::class, 'work_authors')
            ->withPivot(['role', 'position'])
            ->orderByPivot('position');
    }

    /**
     * Expressions where this author is a contributor (translator, etc.).
     */
    public function contributions(): BelongsToMany
    {
        return $this->belongsToMany(Expression::class, 'expression_contributors')
            ->withPivot(['role']);
    }
}