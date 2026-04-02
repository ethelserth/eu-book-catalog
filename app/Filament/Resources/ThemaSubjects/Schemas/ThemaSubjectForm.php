<?php

namespace App\Filament\Resources\ThemaSubjects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemaSubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subject Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Thema Code')
                            ->helperText('Unique Thema code, e.g. FBA, 1KBB')
                            ->required()
                            ->maxLength(10),

                        Select::make('parent_code')
                            ->label('Parent Subject')
                            ->relationship('parent', 'heading_en')
                            ->searchable()
                            ->preload()
                            ->placeholder('Root level (no parent)'),

                        TextInput::make('heading_en')
                            ->label('English Heading')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('heading_el')
                            ->label('Greek Heading')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('level')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(6)
                            ->helperText('Hierarchy depth: 1 = top-level'),
                    ]),
            ]);
    }
}
