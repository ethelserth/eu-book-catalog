<?php

namespace App\Filament\Resources\Expressions\Pages;

use App\Filament\Resources\Expressions\ExpressionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpressions extends ListRecords
{
    protected static string $resource = ExpressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
