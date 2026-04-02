<?php

namespace App\Filament\Resources\Publishers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublisherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('country')
                            ->maxLength(100),

                        TextInput::make('website')
                            ->url()
                            ->maxLength(500),
                    ]),

                Section::make('Authority Control')
                    ->schema([
                        TextInput::make('isni')
                            ->label('ISNI')
                            ->helperText('International Standard Name Identifier — 16-digit code')
                            ->maxLength(20),
                    ]),
            ]);
    }
}
