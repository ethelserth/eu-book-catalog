<?php

namespace App\Filament\Resources\RawIngestionRecords\Pages;

use App\Filament\Resources\RawIngestionRecords\RawIngestionRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRawIngestionRecord extends ViewRecord
{
    protected static string $resource = RawIngestionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
