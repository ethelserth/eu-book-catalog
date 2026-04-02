<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Work extends Model
{
    use HasUuid;

    protected $fillable = [
        'original_title',
        'original_language',
        'description',
        'first_publication_year',
        'wikidata_id',
        'oclc_work_id',
    ];

    protected $casts = [
        'first_publication_year' => 'integer',
    ];

    /**
     * Authors who created this work.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'work_authors')
            ->withPivot(['role', 'position'])
            ->orderByPivot('position');
    }

    /**
     * All expressions (translations, adaptations) of this work.
     */
    public function expressions(): HasMany
    {
        return $this->hasMany(Expression::class);
    }

    /**
     * Thema subject classifications.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(ThemaSubject::class, 'work_subjects', 'work_id', 'thema_code');
    }
}