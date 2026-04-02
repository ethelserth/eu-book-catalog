<?php

namespace App\Filament\Resources\Provenances\Pages;

use App\Filament\Resources\Provenances\ProvenanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProvenance extends ViewRecord
{
    protected static string $resource = ProvenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
