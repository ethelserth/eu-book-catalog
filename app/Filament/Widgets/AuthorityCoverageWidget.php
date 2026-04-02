<?php

namespace App\Filament\Widgets;

use App\Models\Author;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AuthorityCoverageWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $total = Author::count();

        if ($total === 0) {
            return [
                Stat::make('No authors yet', '—')
                    ->description('Import data to see authority coverage'),
            ];
        }

        $withViaf     = Author::whereNotNull('viaf_id')->count();
        $withWikidata = Author::whereNotNull('wikidata_id')->count();
        $withIsni     = Author::whereNotNull('isni')->count();
        $needsReview  = Author::where('needs_review', true)->count();
        $highConfidence = Author::where('authority_confidence', '>=', 0.8)->count();

        return [
            Stat::make('VIAF Coverage', $this->pct($withViaf, $total))
                ->description("{$withViaf} of {$total} authors matched")
                ->color($withViaf / $total >= 0.7 ? 'success' : 'warning')
                ->icon('heroicon-o-check-badge'),

            Stat::make('Wikidata Coverage', $this->pct($withWikidata, $total))
                ->description("{$withWikidata} of {$total} authors linked")
                ->color($withWikidata / $total >= 0.5 ? 'success' : 'warning')
                ->icon('heroicon-o-globe-alt'),

            Stat::make('ISNI Coverage', $this->pct($withIsni, $total))
                ->description("{$withIsni} of {$total} authors identified")
                ->color('info')
                ->icon('heroicon-o-identification'),

            Stat::make('High Confidence', $this->pct($highConfidence, $total))
                ->description('Authority confidence ≥ 0.8')
                ->color($highConfidence / $total >= 0.8 ? 'success' : 'danger')
                ->icon('heroicon-o-shield-check'),

            Stat::make('Needs Review', $needsReview)
                ->description('Authors flagged for manual check')
                ->color($needsReview > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    private function pct(int $count, int $total): string
    {
        return round(($count / $total) * 100) . '%';
    }
}
