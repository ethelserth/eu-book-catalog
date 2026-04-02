<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials\Schemas;

use App\Models\ProviderCredential;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProviderCredentialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Provider')
                ->description('Which data source this record configures.')
                ->columns(2)
                ->schema([
                    Select::make('provider')
                        ->label('Provider')
                        ->options(
                            collect(ProviderCredential::providerDefinitions())
                                ->mapWithKeys(fn ($def, $key) => [$key => $def['label']])
                                ->toArray()
                        )
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Selecting a provider pre-fills defaults. Cannot be changed after creation.')
                        ->disabledOn('edit')
                        // ->live() re-renders the form on change so afterStateUpdated fires.
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (! $state) {
                                return;
                            }

                            $def = ProviderCredential::providerDefinitions()[$state] ?? null;

                            if (! $def) {
                                return;
                            }

                            // Auto-fill the label from the definition.
                            $set('label', $def['label']);

                            // Pre-populate credential and settings KeyValue tables with
                            // the provider's expected keys and sensible defaults.
                            // The user can still edit/delete them before saving.
                            $set('credentials', $def['credential_defaults'] ?? []);
                            $set('settings', $def['setting_defaults'] ?? []);
                        }),

                    TextInput::make('label')
                        ->label('Display Label')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Auto-filled from provider; edit to customise.'),
                ]),

            Section::make('Credentials')
                ->description(
                    'API keys, secrets, and connection details. '.
                    'All values are encrypted at rest using APP_KEY. '.
                    'Refer to the provider definition for expected keys.'
                )
                ->schema([
                    // KeyValue component renders a dynamic key→value table.
                    // The admin adds rows for each required credential key.
                    // Values are encrypted transparently by the model cast.
                    KeyValue::make('credentials')
                        ->label('Credential Fields')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->reorderable(false)
                        ->helperText('Keys are pre-filled when you select a provider above. Values are encrypted at rest.'),
                ]),

            Section::make('Settings')
                ->description('Non-secret configuration for this provider.')
                ->schema([
                    KeyValue::make('settings')
                        ->label('Settings')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->reorderable(false)
                        ->helperText('Keys are pre-filled when you select a provider above. Stored as plain JSON (not encrypted).'),
                ]),

            Section::make('Automation')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Enable this provider for manual and automated ingestion.'),

                    Toggle::make('auto_sync')
                        ->label('Automated Sync')
                        ->helperText(
                            'Include in nightly catalog:sync run. '.
                            'Requires is_active to also be enabled.'
                        ),
                ]),

        ]);
    }
}
