<?php

namespace App\Filament\Resources\Provenances\Pages;

use App\Filament\Resources\Provenances\ProvenanceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProvenance extends EditRecord
{
    protected static string $resource = ProvenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
