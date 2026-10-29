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

class MostReadArticles extends TableWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('الأكثر قراءة (7 أيام)'))
            ->emptyStateHeading(__('لا قراءات مسجّلة بعد'))
            ->emptyStateDescription(__('يظهر الترتيب حين تُسجَّل قراءات حقيقية. لا نرتّب على أرقام تجريبية.'))
            /*
             * Ranked on real reads in the window, not on `views_count`, which
             * was written by nothing and made this widget a ranking of seeded
             * numbers. `article_views` is filled only by genuine readership, so
             * an editor either sees what actually worked or sees nothing.
             *
             * The join is an inner join on purpose: an article with no recorded
             * reads is absent rather than ranked last with a zero.
             */
            ->query(fn (): Builder => ScopedArticles::for(auth()->user())
                ->where('articles.status', ArticleStatus::Published->value)
                ->join('article_views', 'article_views.article_id', '=', 'articles.id')
                ->where('article_views.viewed_on', '>=', now()->subDays((int) config('masar.views.window_days', 7))->toDateString())
                ->with(['category.translations'])
                ->groupBy('articles.id')
                ->select('articles.*')
                ->selectRaw('SUM(article_views.views) as window_views')
                ->orderByDesc('window_views'))
            ->paginated(false)
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('title')
                    ->label(__('العنوان'))
                    ->wrap()
                    ->limit(60)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('category.name')
                    ->label(__('القسم'))
                    ->badge()
                    ->getStateUsing(fn (Article $record): ?string => $record->category?->name),

                // The window's reads, not the lifetime total: the heading says
                // seven days and the number must mean the same thing.
                TextColumn::make('window_views')->label(__('قراءات هذا الأسبوع'))->numeric(),
            ])
            ->recordActions([]);
    }
}
