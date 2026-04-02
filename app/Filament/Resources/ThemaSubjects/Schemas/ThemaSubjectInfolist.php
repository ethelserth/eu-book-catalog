<?php

namespace App\Filament\Resources\ThemaSubjects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemaSubjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subject')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->weight('bold'),

                        TextEntry::make('level')
                            ->badge(),

                        TextEntry::make('heading_en')
                            ->label('English Heading')
                            ->columnSpanFull(),

                        TextEntry::make('heading_el')
                            ->label('Greek Heading')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('parent.heading_en')
                            ->label('Parent Subject')
                            ->placeholder('Root level'),
                    ]),
            ]);
    }
}
