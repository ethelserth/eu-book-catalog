<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expression extends Model
{
    use HasUuid;

    protected $fillable = [
        'work_id',
        'language',
        'title',
        'expression_type',
    ];

    /**
     * The work this expression belongs to.
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Contributors (translators, editors, illustrators, etc.).
     */
    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'expression_contributors')
            ->withPivot(['role']);
    }

    /**
     * All editions of this expression.
     */
    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }
}