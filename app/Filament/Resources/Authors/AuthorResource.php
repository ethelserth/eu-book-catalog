<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\Pages\ViewAuthor;
use App\Filament\Resources\Authors\Schemas\AuthorForm;
use App\Filament\Resources\Authors\Schemas\AuthorInfolist;
use App\Filament\Resources\Authors\Tables\AuthorsTable;
use App\Filament\Resources\Authors\RelationManagers\NameVariantsRelationManager;
use App\Filament\Resources\Authors\RelationManagers\WorksRelationManager;
use App\Models\Author;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getGlobalSearchResultTitle(mixed $record): string
    {
        return $record->display_name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['display_name', 'sort_name'];
    }

    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return array_filter([
            'Nationality' => $record->nationality,
            'Born'        => $record->birth_year,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return AuthorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuthorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NameVariantsRelationManager::class,
            WorksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
            'create' => CreateAuthor::route('/create'),
            'view' => ViewAuthor::route('/{record}'),
            'edit' => EditAuthor::route('/{record}/edit'),
        ];
    }
}
