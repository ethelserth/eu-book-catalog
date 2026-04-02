<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProviderCredentialInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Provider')
                ->columns(2)
                ->schema([
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('label'),
                ]),

            Section::make('Status')
                ->columns(3)
                ->schema([
                    IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),

                    IconEntry::make('auto_sync')
                        ->label('Auto Sync')
                        ->boolean(),

                    TextEntry::make('last_ingestion_at')
                        ->label('Last Ingestion')
                        ->dateTime()
                        ->placeholder('Never'),
                ]),

            Section::make('Credentials')
                ->description('Stored encrypted. Values shown here are decrypted for display.')
                ->schema([
                    // KeyValueEntry renders the array as a key→value table.
                    // Filament reads the 'credentials' attribute; the model's
                    // encrypted:array cast decrypts it automatically.
                    KeyValueEntry::make('credentials')
                        ->label('Credential Fields'),
                ]),

            Section::make('Settings')
                ->schema([
                    KeyValueEntry::make('settings')
                        ->label('Settings'),
                ]),

            Section::make('Audit')
                ->columns(2)
                ->schema([
                    TextEntry::make('last_tested_at')
                        ->label('Last Connection Test')
                        ->dateTime()
                        ->placeholder('Never tested'),

                    TextEntry::make('updated_at')
                        ->label('Last Modified')
                        ->dateTime(),
                ]),

        ]);
    }
}
