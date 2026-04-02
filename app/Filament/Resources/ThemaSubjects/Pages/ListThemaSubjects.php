<?php

namespace App\Filament\Resources\ThemaSubjects\Pages;

use App\Filament\Resources\ThemaSubjects\ThemaSubjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListThemaSubjects extends ListRecords
{
    protected static string $resource = ThemaSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
