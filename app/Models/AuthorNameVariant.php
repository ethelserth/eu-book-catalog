<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorNameVariant extends Model
{
    use HasUuid;

    protected $fillable = [
        'author_id',
        'name',
        'script',
        'source',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}