<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\ScopedArticles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Work in flight, per stage, each tile linking into the filtered table.
 *
 * A count that cannot be clicked is trivia; the point of seeing "6 in
 * fact-check" is going to those six.
 */
class PipelineOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'مسار التحرير';

    /**
     * The stages worth a tile. Terminal states are not pipeline.
     *
     * @var array<int, string>
     */
    private const STAGES = ['writing', 'fact_check', 'editor_review', 'seo', 'ready', 'scheduled'];

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    protected function getStats(): array
    {
        $counts = ScopedArticles::for(auth()->user())
            ->whereIn('status', self::STAGES)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(self::STAGES)
            ->map(function (string $stage) use ($counts): Stat {
                $status = ArticleStatus::from($stage);
                $count = (int) ($counts[$stage] ?? 0);

                return Stat::make($status->label(), (string) $count)
                    ->color($count > 0 ? 'primary' : 'gray')
                    ->url(ArticleResource::getUrl('index', [
                        'tableFilters' => ['status' => ['values' => [$stage]]],
                    ]));
            })
            ->all();
    }
}
