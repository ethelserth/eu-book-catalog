<?php

namespace App\Filament\Resources\ThemaSubjects\Pages;

use App\Filament\Resources\ThemaSubjects\ThemaSubjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewThemaSubject extends ViewRecord
{
    protected static string $resource = ThemaSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
