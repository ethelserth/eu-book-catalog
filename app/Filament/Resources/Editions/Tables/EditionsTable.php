<?php

namespace App\Filament\Resources\Editions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('isbn13')
                    ->label('ISBN-13')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('expression.title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('publisher.name')
                    ->label('Publisher')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('publication_year')
                    ->label('Year')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('format')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'hardback'  => 'gray',
                        'paperback' => 'info',
                        'ebook'     => 'success',
                        'audiobook' => 'warning',
                        default     => 'gray',
                    }),

                TextColumn::make('source_system')
                    ->label('Source')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('format')
                    ->options([
                        'hardback'  => 'Hardback',
                        'paperback' => 'Paperback',
                        'ebook'     => 'E-book',
                        'audiobook' => 'Audiobook',
                    ]),

                SelectFilter::make('publisher')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('source_system')
                    ->label('Source')
                    ->searchable()
                    ->preload(),

                Filter::make('year_range')
                    ->form([
                        TextInput::make('year_from')->numeric()->label('From year'),
                        TextInput::make('year_to')->numeric()->label('To year'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['year_from'], fn ($q) => $q->where('publication_year', '>=', $data['year_from']))
                            ->when($data['year_to'], fn ($q) => $q->where('publication_year', '<=', $data['year_to']));
                    }),
            ])
            ->defaultSort('publication_year', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
