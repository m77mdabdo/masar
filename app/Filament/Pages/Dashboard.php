<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\BlockedByGate;
use App\Filament\Widgets\MostReadArticles;
use App\Filament\Widgets\PipelineOverview;
use App\Filament\Widgets\PublishedThisWeek;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\ScheduledNext48Hours;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The newsroom's morning view, in the order the day is actually worked:
 * what is moving, what is stuck, what went out, what is coming.
 */
class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public function getTitle(): string
    {
        return 'لوحة التحرير';
    }

    public static function getNavigationLabel(): string
    {
        return 'لوحة التحرير';
    }

    public function getWidgets(): array
    {
        return [
            PipelineOverview::class,
            BlockedByGate::class,
            PublishedThisWeek::class,
            MostReadArticles::class,
            ScheduledNext48Hours::class,
            RecentActivity::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
