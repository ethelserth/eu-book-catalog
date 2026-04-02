<?php

namespace App\Filament\Resources\RawIngestionRecords\Pages;

use App\Filament\Resources\RawIngestionRecords\RawIngestionRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRawIngestionRecord extends EditRecord
{
    protected static string $resource = RawIngestionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
