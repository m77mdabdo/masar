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
 * Pure SQL against `gate_failures_count`, which is written wherever the gate
 * already runs. NULL is excluded deliberately: it means never evaluated, not
 * blocked.
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
                ->where('gate_failures_count', '>', 0)
                ->with(['category.translations', 'author:id,name', 'sources']))
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
}
