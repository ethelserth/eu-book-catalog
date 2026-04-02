<?php

namespace App\Filament\Resources\Expressions\Pages;

use App\Filament\Resources\Expressions\ExpressionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExpression extends EditRecord
{
    protected static string $resource = ExpressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
