<?php

namespace App\Filament\Resources\Editions\Schemas;

use App\Filament\Resources\Expressions\ExpressionResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bibliographic Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('expression.title')
                            ->label('Expression')
                            ->weight('bold')
                            ->columnSpanFull()
                            ->url(fn ($record) => $record->expression_id
                                ? ExpressionResource::getUrl('view', ['record' => $record->expression_id])
                                : null),

                        TextEntry::make('publisher.name')
                            ->label('Publisher'),

                        TextEntry::make('format')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'hardback'  => 'gray',
                                'paperback' => 'info',
                                'ebook'     => 'success',
                                'audiobook' => 'warning',
                                default     => 'gray',
                            }),

                        TextEntry::make('isbn13')
                            ->label('ISBN-13')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('isbn10')
                            ->label('ISBN-10')
                            ->placeholder('—'),

                        TextEntry::make('publication_year')
                            ->label('Year')
                            ->placeholder('—'),

                        TextEntry::make('publication_date')
                            ->label('Publication Date')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('pages')
                            ->numeric()
                            ->placeholder('—'),
                    ]),

                Section::make('Provenance')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source_system')
                            ->label('Source')
                            ->badge(),

                        TextEntry::make('source_record_id')
                            ->label('Source Record ID')
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
