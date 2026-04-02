<?php

namespace App\Filament\Resources\RawIngestionRecords\Pages;

use App\Filament\Resources\RawIngestionRecords\RawIngestionRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRawIngestionRecords extends ListRecords
{
    protected static string $resource = RawIngestionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
