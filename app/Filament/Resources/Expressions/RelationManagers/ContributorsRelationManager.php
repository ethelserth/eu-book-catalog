<?php

namespace App\Filament\Resources\Expressions\RelationManagers;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContributorsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributors';

    protected static ?string $relatedResource = AuthorResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'translator'  => 'info',
                        'editor'      => 'warning',
                        'illustrator' => 'success',
                        default       => 'gray',
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['display_name', 'sort_name'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options([
                                'translator'   => 'Translator',
                                'editor'       => 'Editor',
                                'illustrator'  => 'Illustrator',
                                'foreword'     => 'Foreword',
                                'introduction' => 'Introduction',
                            ])
                            ->required()
                            ->default('translator'),
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
