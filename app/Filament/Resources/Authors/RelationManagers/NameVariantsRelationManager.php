<?php

namespace App\Filament\Resources\Authors\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NameVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'nameVariants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name Variant')
                    ->helperText('e.g. Kazantzakis, N. or Καζαντζάκης Ν.')
                    ->required()
                    ->maxLength(500),

                Select::make('script')
                    ->options([
                        'latin'   => 'Latin',
                        'greek'   => 'Greek',
                        'cyrillic'=> 'Cyrillic',
                        'arabic'  => 'Arabic',
                        'other'   => 'Other',
                    ])
                    ->required()
                    ->default('latin'),

                TextInput::make('source')
                    ->helperText('Where this name came from, e.g. biblionet, viaf, manual')
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('script')
                    ->badge(),

                TextColumn::make('source')
                    ->badge()
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
