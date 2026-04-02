<?php

namespace App\Filament\Resources\Provenances\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProvenancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('source_system')
                    ->label('Source')
                    ->badge()
                    ->sortable(),

                TextColumn::make('records_processed')
                    ->label('Processed')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('records_created')
                    ->label('Created')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('records_updated')
                    ->label('Updated')
                    ->numeric()
                    ->color('info'),

                TextColumn::make('records_failed')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('ingestion_started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ingestion_completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->placeholder('In progress…')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_system')
                    ->label('Source')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('ingestion_started_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
