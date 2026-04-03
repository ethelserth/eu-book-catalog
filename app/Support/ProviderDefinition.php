<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Immutable descriptor for a data provider's admin configuration.
 * Holds the label and the default keys/values that pre-fill the
 * Filament credential and settings KeyValue tables when a provider
 * is first selected.
 */
readonly class ProviderDefinition
{
    /**
     * @param  array<string, string>  $credentialDefaults  Key → default value (encrypted at rest).
     * @param  array<string, string>  $settingDefaults  Key → default value (plain JSON).
     */
    public function __construct(
        public string $label,
        public array $credentialDefaults,
        public array $settingDefaults,
    ) {}
}
