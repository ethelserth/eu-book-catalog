<?php

namespace App\Filament\Resources\RawIngestionRecords;

use App\Filament\Resources\RawIngestionRecords\Pages\ListRawIngestionRecords;
use App\Filament\Resources\RawIngestionRecords\Pages\ViewRawIngestionRecord;
use App\Filament\Resources\RawIngestionRecords\Schemas\RawIngestionRecordInfolist;
use App\Filament\Resources\RawIngestionRecords\Tables\RawIngestionRecordsTable;
use App\Models\RawIngestionRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RawIngestionRecordResource extends Resource
{
    protected static ?string $model = RawIngestionRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Ingestion';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'source_record_id';

    // Raw records are created by the ingestion system, not manually.
    // No create or edit pages.

    public static function infolist(Schema $schema): Schema
    {
        return RawIngestionRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RawIngestionRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRawIngestionRecords::route('/'),
            'view' => ViewRawIngestionRecord::route('/{record}'),
        ];
    }
}
