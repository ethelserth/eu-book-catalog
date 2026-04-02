<?php

namespace App\Filament\Resources\Publishers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PublishersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('country')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('isni')
                    ->label('ISNI')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('website')
                    ->placeholder('—')
                    ->url(fn ($state) => $state)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
