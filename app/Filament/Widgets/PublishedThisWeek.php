<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Filament\Support\ScopedArticles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PublishedThisWeek extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'النشر';

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    protected function getStats(): array
    {
        $daily = $this->dailyCounts();
        $thisWeek = array_sum($daily);

        $lastWeek = (int) ScopedArticles::for(auth()->user())
            ->where('status', ArticleStatus::Published->value)
            ->whereBetween('published_at', [now()->subDays(14)->startOfDay(), now()->subDays(7)->endOfDay()])
            ->count();

        $delta = $thisWeek - $lastWeek;

        return [
            Stat::make('منشور هذا الأسبوع', (string) $thisWeek)
                ->description($delta === 0
                    ? 'كما الأسبوع الماضي'
                    : ($delta > 0 ? "+{$delta} عن الأسبوع الماضي" : "{$delta} عن الأسبوع الماضي"))
                ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($delta >= 0 ? 'success' : 'danger')
                ->chart($daily),
        ];
    }

    /**
     * One bucket per day for the last seven, zero-filled — a sparkline with
     * gaps skipped would misrepresent a quiet day as no day.
     *
     * @return array<int, int>
     */
    private function dailyCounts(): array
    {
        $rows = ScopedArticles::for(auth()->user())
            ->where('status', ArticleStatus::Published->value)
            ->where('published_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('date(published_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $series = [];

        foreach (range(6, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();
            $series[] = (int) ($rows[$day] ?? 0);
        }

        return $series;
    }
}
