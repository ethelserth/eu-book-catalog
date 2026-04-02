<?php

namespace App\Filament\Resources\Expressions\Schemas;

use App\Filament\Resources\Works\WorkResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpressionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expression Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('language'),

                        TextEntry::make('expression_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'original'    => 'success',
                                'translation' => 'info',
                                'adaptation'  => 'warning',
                                default       => 'gray',
                            }),

                        TextEntry::make('work.original_title')
                            ->label('Work')
                            ->columnSpanFull()
                            ->url(fn ($record) => $record->work_id
                                ? WorkResource::getUrl('view', ['record' => $record->work_id])
                                : null),
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
