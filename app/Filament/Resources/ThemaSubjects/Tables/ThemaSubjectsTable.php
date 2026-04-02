<?php

namespace App\Filament\Resources\ThemaSubjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ThemaSubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('heading_en')
                    ->label('English Heading')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('heading_el')
                    ->label('Greek Heading')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('parent_code')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('level')
                    ->sortable()
                    ->badge(),

                TextColumn::make('children_count')
                    ->label('Children')
                    ->counts('children')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options([
                        1 => 'Level 1',
                        2 => 'Level 2',
                        3 => 'Level 3',
                        4 => 'Level 4',
                    ]),
            ])
            ->defaultSort('code')
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
