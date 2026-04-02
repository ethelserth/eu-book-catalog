<?php

namespace App\Filament\Resources\ReviewQueues;

use App\Filament\Resources\ReviewQueues\Pages\EditReviewQueue;
use App\Filament\Resources\ReviewQueues\Pages\ListReviewQueues;
use App\Filament\Resources\ReviewQueues\Pages\ViewReviewQueue;
use App\Filament\Resources\ReviewQueues\Schemas\ReviewQueueForm;
use App\Filament\Resources\ReviewQueues\Schemas\ReviewQueueInfolist;
use App\Filament\Resources\ReviewQueues\Tables\ReviewQueuesTable;
use App\Models\ReviewQueue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReviewQueueResource extends Resource
{
    protected static ?string $model = ReviewQueue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'Quality';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'issue_type';

    // Review queue entries are created by the system, not manually — no create page.
    // Edit page is limited to resolution fields only.

    public static function form(Schema $schema): Schema
    {
        return ReviewQueueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReviewQueueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewQueuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewQueues::route('/'),
            'view' => ViewReviewQueue::route('/{record}'),
            'edit' => EditReviewQueue::route('/{record}/edit'),
        ];
    }
}
