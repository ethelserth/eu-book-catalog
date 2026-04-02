<?php

namespace App\Filament\Resources\Expressions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpressionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('language')
                    ->sortable(),

                TextColumn::make('expression_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'original'    => 'success',
                        'translation' => 'info',
                        'adaptation'  => 'warning',
                        default       => 'gray',
                    }),

                TextColumn::make('work.original_title')
                    ->label('Work')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('language')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('expression_type')
                    ->label('Type')
                    ->options([
                        'original'    => 'Original',
                        'translation' => 'Translation',
                        'adaptation'  => 'Adaptation',
                        'abridgement' => 'Abridgement',
                    ]),
            ])
            ->defaultSort('title')
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
