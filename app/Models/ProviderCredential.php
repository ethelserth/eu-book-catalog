<?php

declare(strict_types=1);

namespace App\Models;

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
    public static function forProvider(string $provider): ?self
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
     * e.g. $cred->credential('client_id')
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * Get a single settings value by key.
     * e.g. $cred->setting('rate_limit', 1)
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    // -------------------------------------------------------------------------
    // Known provider definitions
    // Used by the Filament form to render the right credential fields
    // -------------------------------------------------------------------------

    /**
     * Returns the expected credential keys + labels for each known provider.
     * The Filament form uses this to render dynamic fields.
     *
     * @return array<string, array{label: string, fields: array<string, string>}>
     */
    public static function providerDefinitions(): array
    {
        return [
            'biblionet' => [
                'label' => 'BIBLIONET (Greek Book Database)',
                'fields' => [
                    'base_url' => 'Base URL',
                    'client_id' => 'Client ID',
                    'client_secret' => 'Client Secret (encrypted)',
                ],
                'settings' => [
                    'rate_limit' => 'Rate Limit (req/sec)',
                ],
                // Default values pre-filled when this provider is selected in the form.
                'credential_defaults' => [
                    'base_url' => 'https://api.biblionet.gr',
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'setting_defaults' => [
                    'rate_limit' => '2',
                ],
            ],
            'openlibrary' => [
                'label' => 'Open Library (Internet Archive)',
                'fields' => [
                    'user_agent' => 'User-Agent string (e.g. EUCatalog/1.0 (you@email.com))',
                ],
                'settings' => [
                    'rate_limit' => 'Rate Limit (req/sec)',
                    'full_sync_from' => 'Full Sync From (YYYY-MM-DD)',
                ],
                'credential_defaults' => [
                    'user_agent' => 'EUCatalog/1.0 (admin@eucatalog.test)',
                ],
                'setting_defaults' => [
                    'rate_limit' => '3',
                    'full_sync_from' => '',
                ],
            ],
        ];
    }

    /**
     * Convenience: get the human-readable provider label.
     */
    public function providerLabel(): string
    {
        return static::providerDefinitions()[$this->provider]['label'] ?? $this->provider;
    }
}
