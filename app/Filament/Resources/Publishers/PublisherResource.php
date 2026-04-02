<?php

namespace App\Filament\Resources\Publishers;

use App\Filament\Resources\Publishers\Pages\CreatePublisher;
use App\Filament\Resources\Publishers\Pages\EditPublisher;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Filament\Resources\Publishers\Pages\ViewPublisher;
use App\Filament\Resources\Publishers\Schemas\PublisherForm;
use App\Filament\Resources\Publishers\Schemas\PublisherInfolist;
use App\Filament\Resources\Publishers\Tables\PublishersTable;
use App\Filament\Resources\Publishers\RelationManagers\EditionsRelationManager;
use App\Filament\Resources\Publishers\RelationManagers\NameVariantsRelationManager;
use App\Models\Publisher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublisherResource extends Resource
{
    protected static ?string $model = Publisher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGlobalSearchResultTitle(mixed $record): string
    {
        return $record->name;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return array_filter([
            'Country' => $record->country,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PublisherForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PublisherInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublishersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NameVariantsRelationManager::class,
            EditionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublishers::route('/'),
            'create' => CreatePublisher::route('/create'),
            'view' => ViewPublisher::route('/{record}'),
            'edit' => EditPublisher::route('/{record}/edit'),
        ];
    }
}
