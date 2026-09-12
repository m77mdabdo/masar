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
            ->heading('الأكثر قراءة (7 أيام)')
            ->emptyStateHeading('لا بيانات بعد')
            ->query(fn (): Builder => ScopedArticles::for(auth()->user())
                ->where('status', ArticleStatus::Published->value)
                ->where('published_at', '>=', now()->subDays(7))
                ->with(['category.translations'])
                ->orderByDesc('views_count'))
            ->paginated(false)
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->wrap()
                    ->limit(60)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('category.name')
                    ->label('القسم')
                    ->badge()
                    ->getStateUsing(fn (Article $record): ?string => $record->category?->name),

                TextColumn::make('views_count')->label('المشاهدات')->numeric()->sortable(),
            ])
            ->recordActions([]);
    }
}
