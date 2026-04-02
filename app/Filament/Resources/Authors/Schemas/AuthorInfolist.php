<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('display_name')
                            ->label('Display Name')
                            ->columnSpanFull()
                            ->size('lg')
                            ->weight('bold'),

                        TextEntry::make('sort_name')
                            ->label('Sort Name')
                            ->columnSpanFull(),

                        TextEntry::make('nationality')
                            ->placeholder('—'),

                        TextEntry::make('birth_year')
                            ->label('Born')
                            ->placeholder('—'),

                        TextEntry::make('death_year')
                            ->label('Died')
                            ->placeholder('—'),
                    ]),

                Section::make('Authority Control')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('viaf_id')
                            ->label('VIAF ID')
                            ->placeholder('—')
                            ->url(fn ($state) => $state ? 'https://viaf.org/viaf/' . $state : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('wikidata_id')
                            ->label('Wikidata')
                            ->placeholder('—')
                            ->url(fn ($state) => $state ? 'https://www.wikidata.org/wiki/' . $state : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('isni')
                            ->label('ISNI')
                            ->placeholder('—'),

                        TextEntry::make('authority_confidence')
                            ->label('Match Confidence')
                            ->numeric(decimalPlaces: 2)
                            ->badge()
                            ->color(fn ($state) => match (true) {
                                $state >= 0.8  => 'success',
                                $state >= 0.5  => 'warning',
                                default        => 'danger',
                            }),

                        IconEntry::make('needs_review')
                            ->label('Flagged for Review')
                            ->boolean()
                            ->trueColor('warning')
                            ->falseColor('success'),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')
                            ->label('UUID'),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ]),
            ]);
    }
}
