<?php

namespace App\Filament\Resources\Editions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bibliographic Details')
                    ->columns(2)
                    ->schema([
                        Select::make('expression_id')
                            ->label('Expression')
                            ->relationship('expression', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('publisher_id')
                            ->label('Publisher')
                            ->relationship('publisher', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('isbn13')
                            ->label('ISBN-13')
                            ->maxLength(13),

                        TextInput::make('isbn10')
                            ->label('ISBN-10')
                            ->maxLength(10),

                        DatePicker::make('publication_date')
                            ->label('Publication Date'),

                        TextInput::make('publication_year')
                            ->label('Publication Year')
                            ->numeric()
                            ->minValue(1400)
                            ->maxValue(2100),
                    ]),

                Section::make('Physical Details')
                    ->columns(2)
                    ->schema([
                        Select::make('format')
                            ->options([
                                'hardback'  => 'Hardback',
                                'paperback' => 'Paperback',
                                'ebook'     => 'E-book',
                                'audiobook' => 'Audiobook',
                            ])
                            ->required()
                            ->default('paperback'),

                        TextInput::make('pages')
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('cover_url')
                            ->label('Cover URL')
                            ->url()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Provenance')
                    ->columns(2)
                    ->schema([
                        TextInput::make('source_system')
                            ->label('Source System')
                            ->helperText('e.g. biblionet, manual')
                            ->required(),

                        TextInput::make('source_record_id')
                            ->label('Source Record ID'),
                    ]),
            ]);
    }
}
