<?php

namespace App\Filament\Resources\Works\Pages;

use App\Filament\Resources\Works\WorkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWork extends ViewRecord
{
    protected static string $resource = WorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
