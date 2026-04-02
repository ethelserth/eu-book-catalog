<?php

namespace App\Filament\Resources\Expressions\RelationManagers;

use App\Filament\Resources\Editions\EditionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'editions';

    protected static ?string $relatedResource = EditionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('isbn13')
                    ->label('ISBN-13')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('publisher.name')
                    ->label('Publisher'),

                TextColumn::make('publication_year')
                    ->label('Year')
                    ->placeholder('—'),

                TextColumn::make('format')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'hardback'  => 'gray',
                        'paperback' => 'info',
                        'ebook'     => 'success',
                        'audiobook' => 'warning',
                        default     => 'gray',
                    }),

                TextColumn::make('source_system')
                    ->label('Source')
                    ->badge(),
            ]);
    }
}
