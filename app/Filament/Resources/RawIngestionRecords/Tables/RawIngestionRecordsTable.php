<?php

namespace App\Filament\Resources\RawIngestionRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RawIngestionRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_system')
                    ->label('Source')
                    ->badge()
                    ->sortable(),

                TextColumn::make('source_record_id')
                    ->label('Record ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending'    => 'gray',
                        'processing' => 'info',
                        'completed'  => 'success',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                TextColumn::make('retry_count')
                    ->label('Retries')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('fetched_at')
                    ->label('Fetched')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'processing' => 'Processing',
                        'completed'  => 'Completed',
                        'failed'     => 'Failed',
                    ]),

                SelectFilter::make('source_system')
                    ->label('Source')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('fetched_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
