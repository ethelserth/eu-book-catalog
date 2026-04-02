<?php

namespace App\Filament\Resources\Publishers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublisherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('country')
                            ->placeholder('—'),

                        TextEntry::make('website')
                            ->placeholder('—')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab(),

                        TextEntry::make('isni')
                            ->label('ISNI')
                            ->placeholder('—'),

                        TextEntry::make('editions_count')
                            ->label('Editions')
                            ->state(fn ($record) => $record->editions()->count()),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')->label('UUID'),
                        TextEntry::make('created_at')->label('Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Last Updated')->dateTime(),
                    ]),
            ]);
    }
}
