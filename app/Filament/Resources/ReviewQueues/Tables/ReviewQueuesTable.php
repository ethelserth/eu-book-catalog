<?php

namespace App\Filament\Resources\ReviewQueues\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewQueuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'author'    => 'info',
                        'work'      => 'success',
                        'edition'   => 'gray',
                        'publisher' => 'warning',
                        default     => 'gray',
                    }),

                TextColumn::make('issue_type')
                    ->label('Issue')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'low_confidence_match'    => 'warning',
                        'duplicate_candidate'     => 'danger',
                        'missing_authority'       => 'gray',
                        'manual_review_requested' => 'info',
                        default                   => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending'  => 'warning',
                        'resolved' => 'success',
                        'ignored'  => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('resolver.name')
                    ->label('Resolved By')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Flagged')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'resolved' => 'Resolved',
                        'ignored'  => 'Ignored',
                    ]),

                SelectFilter::make('entity_type')
                    ->label('Entity Type')
                    ->options([
                        'author'    => 'Author',
                        'work'      => 'Work',
                        'edition'   => 'Edition',
                        'publisher' => 'Publisher',
                    ]),

                SelectFilter::make('issue_type')
                    ->label('Issue Type')
                    ->options([
                        'low_confidence_match'    => 'Low Confidence Match',
                        'duplicate_candidate'     => 'Duplicate Candidate',
                        'missing_authority'       => 'Missing Authority',
                        'manual_review_requested' => 'Manual Review',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('Resolve'),
            ]);
    }
}
