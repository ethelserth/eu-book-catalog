<?php

namespace App\Filament\Resources\Works\Tables;

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

class WorksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('original_language')
                    ->label('Language')
                    ->sortable(),

                TextColumn::make('first_publication_year')
                    ->label('Year')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('authors.display_name')
                    ->label('Authors')
                    ->listWithLineBreaks()
                    ->limitList(2),

                TextColumn::make('expressions_count')
                    ->label('Expressions')
                    ->counts('expressions')
                    ->sortable(),

                TextColumn::make('wikidata_id')
                    ->label('Wikidata')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('original_language')
                    ->label('Language')
                    ->searchable()
                    ->preload(),

                Filter::make('has_wikidata')
                    ->label('Has Wikidata ID')
                    ->query(fn (Builder $query) => $query->whereNotNull('wikidata_id'))
                    ->toggle(),

                Filter::make('year_range')
                    ->form([
                        TextInput::make('year_from')->numeric()->label('From year'),
                        TextInput::make('year_to')->numeric()->label('To year'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['year_from'], fn ($q) => $q->where('first_publication_year', '>=', $data['year_from']))
                            ->when($data['year_to'], fn ($q) => $q->where('first_publication_year', '<=', $data['year_to']));
                    }),
            ])
            ->defaultSort('original_title')
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
