<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ProviderDefinition;
use Filament\Support\Contracts\HasLabel;

enum ProviderType: string implements HasLabel
{
    case Biblionet = 'biblionet';
    case OpenLibrary = 'openlibrary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Biblionet => 'BIBLIONET (Greek Book Database)',
            self::OpenLibrary => 'Open Library (Internet Archive)',
        };
    }

    public function definition(): ProviderDefinition
    {
        return match ($this) {
            self::Biblionet => new ProviderDefinition(
                label: $this->getLabel(),
                credentialDefaults: [
                    'username' => '',
                    'password' => '',
                ],
                settingDefaults: [
                    'timeout' => '30',
                ],
            ),
            self::OpenLibrary => new ProviderDefinition(
                label: $this->getLabel(),
                credentialDefaults: [
                    'user_agent' => 'EUCatalog/1.0 (admin@eucatalog.test)',
                ],
                settingDefaults: [
                    'rate_limit' => '3',
                    'full_sync_from' => '',
                ],
            ),
        };
    }
}
