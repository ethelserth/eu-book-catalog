<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot the trait.
     *
     * Laravel automatically calls boot{TraitName} when a model is instantiated.
     * We hook into the "creating" event to generate a UUID before insertion.
     */
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();

            if (empty($model->{$keyName})) {
                $model->{$keyName} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Tell Eloquent that the primary key is not auto-incrementing.
     * Without this, Eloquent expects the database to generate the ID.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Tell Eloquent the primary key is a string, not an integer.
     * Without this, Eloquent would cast "550e8400-..." to 0.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
