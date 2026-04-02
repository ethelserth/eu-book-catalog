<?php

namespace App\Filament\Resources\Authors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('sort_name')
                    ->label('Sort Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('birth_year')
                    ->label('Born')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('death_year')
                    ->label('Died')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('nationality')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('authority_confidence')
                    ->label('Confidence')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($state) => match (true) {
                        $state >= 0.8  => 'success',
                        $state >= 0.5  => 'warning',
                        default        => 'danger',
                    }),

                IconColumn::make('viaf_id')
                    ->label('VIAF')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->state(fn ($record) => filled($record->viaf_id)),

                IconColumn::make('needs_review')
                    ->label('Needs Review')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success'),

                TextColumn::make('works_count')
                    ->label('Works')
                    ->counts('works')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('needs_review')
                    ->label('Needs Review')
                    ->query(fn (Builder $query) => $query->where('needs_review', true))
                    ->toggle(),

                Filter::make('has_viaf')
                    ->label('Has VIAF ID')
                    ->query(fn (Builder $query) => $query->whereNotNull('viaf_id'))
                    ->toggle(),

                Filter::make('no_authority')
                    ->label('No Authority ID')
                    ->query(fn (Builder $query) => $query->whereNull('viaf_id')->whereNull('wikidata_id')->whereNull('isni'))
                    ->toggle(),

                SelectFilter::make('nationality')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('display_name')
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
