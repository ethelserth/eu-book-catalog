<?php

namespace App\Filament\Resources\Provenances;

use App\Filament\Resources\Provenances\Pages\ListProvenances;
use App\Filament\Resources\Provenances\Pages\ViewProvenance;
use App\Filament\Resources\Provenances\Schemas\ProvenanceInfolist;
use App\Filament\Resources\Provenances\Tables\ProvenancesTable;
use App\Models\Provenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProvenanceResource extends Resource
{
    protected static ?string $model = Provenance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static \UnitEnum|string|null $navigationGroup = 'Ingestion';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'batch_id';

    // Provenance records are created by the ingestion system, not manually.
    // No create or edit pages.

    public static function infolist(Schema $schema): Schema
    {
        return ProvenanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvenancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvenances::route('/'),
            'view' => ViewProvenance::route('/{record}'),
        ];
    }
}
