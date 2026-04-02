<?php

namespace App\Filament\Resources\Works\RelationManagers;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthorsRelationManager extends RelationManager
{
    protected static string $relationship = 'authors';

    protected static ?string $relatedResource = AuthorResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Author')
                    ->searchable(),

                TextColumn::make('sort_name')
                    ->label('Sort Name')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Pivot fields
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge(),

                TextColumn::make('pivot.position')
                    ->label('Position')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['display_name', 'sort_name'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options([
                                'author'      => 'Author',
                                'editor'      => 'Editor',
                                'illustrator' => 'Illustrator',
                                'photographer'=> 'Photographer',
                            ])
                            ->required()
                            ->default('author'),
                        TextInput::make('position')
                            ->numeric()
                            ->default(1)
                            ->helperText('Order among co-authors (1 = primary)'),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
