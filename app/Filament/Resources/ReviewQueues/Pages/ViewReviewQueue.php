<?php

namespace App\Filament\Resources\ReviewQueues\Pages;

use App\Filament\Resources\ReviewQueues\ReviewQueueResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReviewQueue extends ViewRecord
{
    protected static string $resource = ReviewQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
