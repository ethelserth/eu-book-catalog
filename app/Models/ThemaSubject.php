<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemaSubject extends Model
{
    // No HasUuid - we use the Thema code as primary key
    // No timestamps - reference data doesn't change
    
    public $timestamps = false;
    
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'parent_code',
        'heading_en',
        'heading_el',
        'level',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    /**
     * Parent subject in the hierarchy.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ThemaSubject::class, 'parent_code', 'code');
    }

    /**
     * Child subjects.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ThemaSubject::class, 'parent_code', 'code');
    }

    /**
     * Works classified under this subject.
     */
    public function works(): BelongsToMany
    {
        return $this->belongsToMany(Work::class, 'work_subjects', 'thema_code', 'work_id');
    }
}