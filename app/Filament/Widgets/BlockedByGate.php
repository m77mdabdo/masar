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
 * Articles declared finished that the gate will not let out.
 *
 * This is the widget an editor-in-chief acts on: every row is someone waiting,
 * and the failing rule is shown so the fix is obvious without opening the piece.
 *
 * Like the table filter, the gate itself is PHP, so this narrows in SQL to the
 * only statuses that can be blocked and evaluates those. It is bounded by the
 * size of the ready queue, not the archive — and it gets a denormalised column
 * in TASK 04.
 */
class BlockedByGate extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('محجوبة ببوابة النشر')
            ->description('مواد أُعلن اكتمالها ولا يمكن نشرها بعد.')
            ->emptyStateHeading('لا شيء محجوب')
            ->emptyStateDescription('كل المواد الجاهزة تستوفي شروط النشر.')
            ->query(fn (): Builder => ScopedArticles::for(auth()->user())
                ->whereIn('status', [ArticleStatus::Ready->value, ArticleStatus::Scheduled->value])
                ->whereIn('id', $this->blockedIds())
                ->with(['category.translations', 'author:id,name']))
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->wrap()
                    ->limit(70)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('gate')
                    ->label('الناقص')
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(fn (Article $record): array => $record->isPublishable())
                    ->wrap(),

                TextColumn::make('author.name')->label('الكاتب')->placeholder('—'),
            ])
            ->recordActions([]);
    }

    /**
     * @return array<int, int>
     */
    private function blockedIds(): array
    {
        $ids = ScopedArticles::for(auth()->user())
            ->whereIn('status', [ArticleStatus::Ready->value, ArticleStatus::Scheduled->value])
            ->with('sources')
            ->get()
            ->filter(fn (Article $article): bool => $article->isPublishable() !== [])
            ->pluck('id')
            ->all();

        // whereIn([]) matches everything in some drivers; a sentinel keeps the
        // empty case meaning "nothing".
        return $ids === [] ? [0] : $ids;
    }
}
