<?php

namespace App\Filament\Resources\ReviewQueues\Pages;

use App\Filament\Resources\ReviewQueues\ReviewQueueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReviewQueues extends ListRecords
{
    protected static string $resource = ReviewQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
