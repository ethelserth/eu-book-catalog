<?php

namespace App\Filament\Resources\RawIngestionRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RawIngestionRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Record Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source_system')
                            ->label('Source')
                            ->badge(),

                        TextEntry::make('source_record_id')
                            ->label('Source Record ID'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pending'    => 'gray',
                                'processing' => 'info',
                                'completed'  => 'success',
                                'failed'     => 'danger',
                                default      => 'gray',
                            }),

                        TextEntry::make('retry_count')
                            ->label('Retry Count')
                            ->numeric()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                        TextEntry::make('fetched_at')
                            ->label('Fetched')
                            ->dateTime(),

                        TextEntry::make('processed_at')
                            ->label('Processed')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),

                Section::make('Linked Records')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('provenance.batch_id')
                            ->label('Provenance Batch')
                            ->placeholder('—'),

                        TextEntry::make('edition.isbn13')
                            ->label('Resulting Edition (ISBN)')
                            ->placeholder('—'),
                    ]),

                Section::make('Error')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('')
                            ->placeholder('No error.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Raw Payload')
                    ->schema([
                        TextEntry::make('payload')
                            ->label('')
                            ->state(fn ($record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
