<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Support\ScopedArticles;
use App\Models\Article;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * What goes out in the next 48 hours.
 *
 * The gate column matters here: an article scheduled while complete and then
 * edited into an incomplete state will not publish, and the newsroom should find
 * that out now rather than at 6am when the slot passes silently.
 */
class ScheduledNext48Hours extends TableWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('مجدول خلال 48 ساعة')
            ->emptyStateHeading('لا شيء مجدول')
            ->query(fn (): Builder => ScopedArticles::for(auth()->user())
                ->where('status', ArticleStatus::Scheduled->value)
                ->whereBetween('scheduled_for', [now(), now()->addHours(48)])
                ->with('sources')
                ->orderBy('scheduled_for'))
            ->paginated(false)
            ->columns([
                TextColumn::make('scheduled_for')
                    ->label('الموعد')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (Article $record): string => $record->scheduled_for?->diffForHumans() ?? ''),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->wrap()
                    ->limit(60)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('gate')
                    ->label('الجاهزية')
                    ->badge()
                    ->getStateUsing(fn (Article $record): string => $record->isPublishable() === []
                        ? 'جاهزة'
                        : count($record->isPublishable()).' متطلب ناقص')
                    ->color(fn (Article $record): string => $record->isPublishable() === [] ? 'success' : 'danger'),
            ])
            ->recordActions([]);
    }
}
