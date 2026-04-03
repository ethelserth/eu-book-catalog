<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProviderCredential extends Model
{
    use HasUuid;

    protected $fillable = [
        'provider',
        'label',
        'is_active',
        'credentials',
        'settings',
        'auto_sync',
        'last_tested_at',
        'last_ingestion_at',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        // 'encrypted' cast: Laravel transparently encrypts on write and
        // decrypts on read using APP_KEY + AES-256-CBC.
        // The DB stores ciphertext; your PHP code sees plaintext.
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'last_tested_at' => 'datetime',
        'last_ingestion_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    /**
     * Retrieve the credential record for a specific provider.
     * Returns null if no record exists yet.
     */
    public static function forProvider(ProviderType $provider): ?self
    {
        return static::where('provider', $provider)->first();
    }

    /**
     * All providers that are active and have auto_sync enabled.
     * Used by the CatalogSync command to know what to run.
     *
     * @return Collection<int, self>
     */
    public static function activeAutoSync(): Collection
    {
        return static::where('is_active', true)
            ->where('auto_sync', true)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Credential accessors
    // -------------------------------------------------------------------------

    /**
     * Get a single credential value by key.
     * e.g. $cred->credential('username')
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * Get a single settings value by key.
     * e.g. $cred->setting('timeout', 30)
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Convenience: get the human-readable provider label via the enum.
     */
    public function providerLabel(): string
    {
        return $this->provider->getLabel();
    }
}
