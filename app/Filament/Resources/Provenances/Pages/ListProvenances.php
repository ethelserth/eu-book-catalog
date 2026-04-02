<?php

namespace App\Filament\Resources\Provenances\Pages;

use App\Filament\Resources\Provenances\ProvenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProvenances extends ListRecords
{
    protected static string $resource = ProvenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
