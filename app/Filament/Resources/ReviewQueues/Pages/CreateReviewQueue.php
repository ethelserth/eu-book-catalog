<?php

namespace App\Filament\Resources\ReviewQueues\Pages;

use App\Filament\Resources\ReviewQueues\ReviewQueueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReviewQueue extends CreateRecord
{
    protected static string $resource = ReviewQueueResource::class;
}
