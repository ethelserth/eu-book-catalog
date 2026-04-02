<?php

namespace App\Filament\Resources\Works\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('original_title')
                            ->label('Original Title')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('original_language')
                            ->label('Original Language')
                            ->helperText('ISO 639-3 code, e.g. ell, eng, fra')
                            ->required()
                            ->maxLength(10),

                        TextInput::make('first_publication_year')
                            ->label('First Publication Year')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(2100),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('External Identifiers')
                    ->columns(2)
                    ->schema([
                        TextInput::make('wikidata_id')
                            ->label('Wikidata ID')
                            ->helperText('e.g. Q175583')
                            ->maxLength(20),

                        TextInput::make('oclc_work_id')
                            ->label('OCLC Work ID')
                            ->maxLength(50),
                    ]),
            ]);
    }
}
