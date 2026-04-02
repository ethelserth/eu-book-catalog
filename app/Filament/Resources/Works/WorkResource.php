<?php

namespace App\Filament\Resources\Works;

use App\Filament\Resources\Works\Pages\CreateWork;
use App\Filament\Resources\Works\Pages\EditWork;
use App\Filament\Resources\Works\Pages\ListWorks;
use App\Filament\Resources\Works\Pages\ViewWork;
use App\Filament\Resources\Works\Schemas\WorkForm;
use App\Filament\Resources\Works\Schemas\WorkInfolist;
use App\Filament\Resources\Works\Tables\WorksTable;
use App\Filament\Resources\Works\RelationManagers\AuthorsRelationManager;
use App\Filament\Resources\Works\RelationManagers\ExpressionsRelationManager;
use App\Models\Work;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkResource extends Resource
{
    protected static ?string $model = Work::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'original_title';

    public static function getGlobalSearchResultTitle(mixed $record): string
    {
        return $record->original_title;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['original_title'];
    }

    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return [
            'Language' => $record->original_language,
            'Year'     => $record->first_publication_year ?? '—',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WorkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuthorsRelationManager::class,
            ExpressionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorks::route('/'),
            'create' => CreateWork::route('/create'),
            'view' => ViewWork::route('/{record}'),
            'edit' => EditWork::route('/{record}/edit'),
        ];
    }
}
