<?php

namespace App\Filament\Widgets;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Expression;
use App\Models\Publisher;
use App\Models\ReviewQueue;
use App\Models\Work;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $pendingReview = ReviewQueue::where('status', 'pending')->count();

        return [
            Stat::make('Works', Work::count())
                ->description('Abstract intellectual creations')
                ->color('success')
                ->icon('heroicon-o-book-open'),

            Stat::make('Expressions', Expression::count())
                ->description('Originals, translations, adaptations')
                ->color('info')
                ->icon('heroicon-o-language'),

            Stat::make('Editions', Edition::count())
                ->description('Physical and digital manifestations')
                ->color('gray')
                ->icon('heroicon-o-document-text'),

            Stat::make('Authors', Author::count())
                ->description('Deduplicated via authority control')
                ->color('warning')
                ->icon('heroicon-o-user'),

            Stat::make('Publishers', Publisher::count())
                ->color('gray')
                ->icon('heroicon-o-building-library'),

            Stat::make('Pending Review', $pendingReview)
                ->description('Items flagged for human review')
                ->color($pendingReview > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clipboard-document-check'),
        ];
    }
}
