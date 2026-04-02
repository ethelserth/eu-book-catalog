<?php

namespace App\Filament\Resources\Editions\Pages;

use App\Filament\Resources\Editions\EditionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEdition extends ViewRecord
{
    protected static string $resource = EditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
