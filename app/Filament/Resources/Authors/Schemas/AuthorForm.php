<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('How the author is displayed and sorted in the catalog.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Display Name')
                            ->helperText('e.g. Νίκος Καζαντζάκης or Nikos Kazantzakis')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('sort_name')
                            ->label('Sort Name')
                            ->helperText('Surname-first form, e.g. Καζαντζάκης, Νίκος')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('nationality')
                            ->maxLength(100),

                        TextInput::make('birth_year')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2100),

                        TextInput::make('death_year')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2100),
                    ]),

                Section::make('Authority Control')
                    ->description('External identifiers that uniquely identify this author across databases.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('viaf_id')
                            ->label('VIAF ID')
                            ->helperText('Virtual International Authority File — e.g. 17220308')
                            ->maxLength(50),

                        TextInput::make('isni')
                            ->label('ISNI')
                            ->helperText('International Standard Name Identifier — 16-digit code')
                            ->maxLength(20),

                        TextInput::make('wikidata_id')
                            ->label('Wikidata ID')
                            ->helperText('e.g. Q185085')
                            ->maxLength(20),

                        TextInput::make('authority_confidence')
                            ->label('Match Confidence')
                            ->helperText('0.0–1.0. Records below 0.8 go to review queue.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->default(0),
                    ]),

                Section::make('Review Status')
                    ->schema([
                        Toggle::make('needs_review')
                            ->label('Flag for Review')
                            ->helperText('Set automatically when authority confidence is below 0.8. Can also be set manually.'),
                    ]),
            ]);
    }
}
