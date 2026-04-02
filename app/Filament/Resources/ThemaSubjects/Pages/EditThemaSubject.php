<?php

namespace App\Filament\Resources\ThemaSubjects\Pages;

use App\Filament\Resources\ThemaSubjects\ThemaSubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditThemaSubject extends EditRecord
{
    protected static string $resource = ThemaSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
