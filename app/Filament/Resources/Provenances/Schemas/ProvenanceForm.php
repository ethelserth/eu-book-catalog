<?php

namespace App\Filament\Resources\Provenances\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProvenanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_system')
                    ->required(),
                TextInput::make('source_url')
                    ->url(),
                TextInput::make('batch_id')
                    ->required(),
                DateTimePicker::make('ingestion_started_at')
                    ->required(),
                DateTimePicker::make('ingestion_completed_at'),
                TextInput::make('records_processed')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('records_created')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('records_updated')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('records_failed')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('error_log')
                    ->columnSpanFull(),
            ]);
    }
}
