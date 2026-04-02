<?php

namespace App\Filament\Resources\Editions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProvenanceLogRelationManager extends RelationManager
{
    protected static string $relationship = 'provenanceLog';

    protected static ?string $title = 'Change History';

    // Immutable audit log — no create, edit, or delete
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('action')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('provenance.batch_id')
                    ->label('Batch')
                    ->searchable(),

                TextColumn::make('provenance.source_system')
                    ->label('Source')
                    ->badge(),

                TextColumn::make('previous_data')
                    ->label('Previous Values')
                    ->state(fn ($record) => $record->previous_data
                        ? json_encode($record->previous_data, JSON_UNESCAPED_UNICODE)
                        : '—')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
