<?php

namespace App\Filament\Resources\Works\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('original_title')
                            ->label('Original Title')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('original_language')
                            ->label('Language'),

                        TextEntry::make('first_publication_year')
                            ->label('First Published')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('External Identifiers')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('wikidata_id')
                            ->label('Wikidata')
                            ->placeholder('—')
                            ->url(fn ($state) => $state ? 'https://www.wikidata.org/wiki/' . $state : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('oclc_work_id')
                            ->label('OCLC Work ID')
                            ->placeholder('—'),
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
