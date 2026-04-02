<?php

namespace App\Filament\Resources\Authors\RelationManagers;

use App\Filament\Resources\Works\WorkResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorksRelationManager extends RelationManager
{
    protected static string $relationship = 'works';

    protected static ?string $relatedResource = WorkResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_title')
                    ->label('Title')
                    ->searchable(),

                TextColumn::make('original_language')
                    ->label('Language'),

                TextColumn::make('first_publication_year')
                    ->label('Year')
                    ->placeholder('—'),

                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge(),

                TextColumn::make('pivot.position')
                    ->label('Position')
                    ->numeric(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
