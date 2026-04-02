<?php

namespace App\Filament\Resources\Expressions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpressionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expression Details')
                    ->columns(2)
                    ->schema([
                        Select::make('work_id')
                            ->label('Work')
                            ->relationship('work', 'original_title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label('Expression Title')
                            ->helperText('Title in the expression\'s language')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('language')
                            ->helperText('ISO 639-3 code, e.g. ell, eng, fra')
                            ->required()
                            ->maxLength(10),

                        Select::make('expression_type')
                            ->label('Type')
                            ->options([
                                'original'    => 'Original',
                                'translation' => 'Translation',
                                'adaptation'  => 'Adaptation',
                                'abridgement' => 'Abridgement',
                            ])
                            ->required()
                            ->default('original'),
                    ]),
            ]);
    }
}
