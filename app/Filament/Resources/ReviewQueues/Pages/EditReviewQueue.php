<?php

namespace App\Filament\Resources\ReviewQueues\Pages;

use App\Filament\Resources\ReviewQueues\ReviewQueueResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewQueue extends EditRecord
{
    protected static string $resource = ReviewQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['status'], ['resolved', 'ignored'])) {
            $data['resolved_by'] = auth()->id();
            $data['resolved_at'] = now();
        } else {
            // If re-opened to pending, clear resolution
            $data['resolved_by'] = null;
            $data['resolved_at'] = null;
        }

        return $data;
    }
}
