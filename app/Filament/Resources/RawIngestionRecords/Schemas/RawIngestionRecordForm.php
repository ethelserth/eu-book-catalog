<?php

namespace App\Filament\Resources\RawIngestionRecords\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RawIngestionRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_system')
                    ->required(),
                TextInput::make('source_record_id')
                    ->required(),
                TextInput::make('payload')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Select::make('provenance_id')
                    ->relationship('provenance', 'id'),
                Select::make('edition_id')
                    ->relationship('edition', 'id'),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                TextInput::make('retry_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('fetched_at')
                    ->required(),
                DateTimePicker::make('processed_at'),
            ]);
    }
}
