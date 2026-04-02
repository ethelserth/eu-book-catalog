<?php

namespace App\Filament\Resources\Editions;

use App\Filament\Resources\Editions\Pages\CreateEdition;
use App\Filament\Resources\Editions\Pages\EditEdition;
use App\Filament\Resources\Editions\Pages\ListEditions;
use App\Filament\Resources\Editions\Pages\ViewEdition;
use App\Filament\Resources\Editions\Schemas\EditionForm;
use App\Filament\Resources\Editions\Schemas\EditionInfolist;
use App\Filament\Resources\Editions\Tables\EditionsTable;
use App\Filament\Resources\Editions\RelationManagers\ProvenanceLogRelationManager;
use App\Models\Edition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EditionResource extends Resource
{
    protected static ?string $model = Edition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'isbn13';

    public static function getGlobalSearchResultTitle(mixed $record): string
    {
        return $record->isbn13 ?? $record->expression?->title ?? 'Edition';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['isbn13', 'isbn10'];
    }

    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return array_filter([
            'Publisher' => $record->publisher?->name,
            'Year'      => $record->publication_year,
            'Format'    => $record->format,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return EditionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EditionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EditionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProvenanceLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEditions::route('/'),
            'create' => CreateEdition::route('/create'),
            'view' => ViewEdition::route('/{record}'),
            'edit' => EditEdition::route('/{record}/edit'),
        ];
    }
}
