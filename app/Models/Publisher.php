<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'country',
        'isni',
        'website',
    ];

    public function nameVariants(): HasMany
    {
        return $this->hasMany(PublisherNameVariant::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }
}