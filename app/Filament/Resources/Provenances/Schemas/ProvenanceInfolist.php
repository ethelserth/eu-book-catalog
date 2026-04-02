<?php

namespace App\Filament\Resources\Provenances\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProvenanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Summary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('batch_id')
                            ->label('Batch ID')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('source_system')
                            ->label('Source')
                            ->badge(),

                        TextEntry::make('source_url')
                            ->label('Source URL')
                            ->placeholder('—')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab(),
                    ]),

                Section::make('Timing')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ingestion_started_at')
                            ->label('Started')
                            ->dateTime(),

                        TextEntry::make('ingestion_completed_at')
                            ->label('Completed')
                            ->dateTime()
                            ->placeholder('In progress…'),
                    ]),

                Section::make('Record Counts')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('records_processed')
                            ->label('Processed')
                            ->numeric(),

                        TextEntry::make('records_created')
                            ->label('Created')
                            ->numeric()
                            ->color('success'),

                        TextEntry::make('records_updated')
                            ->label('Updated')
                            ->numeric()
                            ->color('info'),

                        TextEntry::make('records_failed')
                            ->label('Failed')
                            ->numeric()
                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                    ]),

                Section::make('Error Log')
                    ->schema([
                        TextEntry::make('error_log')
                            ->label('')
                            ->placeholder('No errors recorded.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
