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

    /**
     * Declared even though every widget on it carries its own gate, because a
     * page with no access check is open to every authenticated user and the
     * next widget added here would inherit that rather than the gate
     * (CLAUDE.md §5). `PanelAccessTest` requires this method on every page.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    public function getTitle(): string
    {
        return __('لوحة التحرير');
    }

    public static function getNavigationLabel(): string
    {
        return __('لوحة التحرير');
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
