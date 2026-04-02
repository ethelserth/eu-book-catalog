<?php

namespace App\Filament\Resources\Expressions\Pages;

use App\Filament\Resources\Expressions\ExpressionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExpression extends ViewRecord
{
    protected static string $resource = ExpressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
