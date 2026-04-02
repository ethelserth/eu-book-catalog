<?php

namespace App\Filament\Resources\ReviewQueues\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewQueueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Issue')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('entity_type')
                            ->label('Entity Type')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'author'    => 'info',
                                'work'      => 'success',
                                'edition'   => 'gray',
                                'publisher' => 'warning',
                                default     => 'gray',
                            }),

                        TextEntry::make('issue_type')
                            ->label('Issue Type')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'low_confidence_match'    => 'warning',
                                'duplicate_candidate'     => 'danger',
                                'missing_authority'       => 'gray',
                                'manual_review_requested' => 'info',
                                default                   => 'gray',
                            }),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pending'  => 'warning',
                                'resolved' => 'success',
                                'ignored'  => 'gray',
                                default    => 'gray',
                            }),

                        TextEntry::make('entity_id')
                            ->label('Entity UUID'),
                    ]),

                Section::make('Details')
                    ->schema([
                        TextEntry::make('details')
                            ->label('')
                            ->state(fn ($record) => json_encode($record->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('resolver.name')
                            ->label('Resolved By')
                            ->placeholder('—'),

                        TextEntry::make('resolved_at')
                            ->label('Resolved At')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Flagged At')
                            ->dateTime(),
                    ]),
            ]);
    }
}
