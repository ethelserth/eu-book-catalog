<?php

namespace App\Filament\Resources\Works\RelationManagers;

use App\Filament\Resources\Expressions\ExpressionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpressionsRelationManager extends RelationManager
{
    protected static string $relationship = 'expressions';

    protected static ?string $relatedResource = ExpressionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),

                TextColumn::make('language'),

                TextColumn::make('expression_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'original'    => 'success',
                        'translation' => 'info',
                        'adaptation'  => 'warning',
                        default       => 'gray',
                    }),

                TextColumn::make('editions_count')
                    ->label('Editions')
                    ->counts('editions'),
            ]);
    }
}
