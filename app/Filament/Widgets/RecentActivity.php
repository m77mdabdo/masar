<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\ScopedArticles;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * The last fifteen audit entries.
 *
 * Scoped like everything else: a writer sees activity on their own articles.
 * An audit feed that leaks the newsroom's movements to someone who cannot see
 * the articles themselves would be a permissions hole wearing a widget.
 */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('آخر النشاط')
            ->emptyStateHeading('لا نشاط بعد')
            ->query(fn (): Builder => $this->scopedActivity())
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التوقيت')
                    ->since()
                    ->tooltip(fn (Activity $record): string => $record->created_at?->format('Y-m-d H:i') ?? ''),

                TextColumn::make('description')->label('الحدث')->wrap()->limit(50),

                TextColumn::make('causer.name')->label('المستخدم')->placeholder('النظام'),

                TextColumn::make('subject.title')
                    ->label('المادة')
                    ->wrap()
                    ->limit(45)
                    ->placeholder('—'),
            ])
            ->recordActions([]);
    }

    private function scopedActivity(): Builder
    {
        $query = Activity::query()->with(['causer', 'subject'])->limit(15);

        if (ScopedArticles::isNewsroomWide(auth()->user())) {
            return $query;
        }

        $visibleIds = ScopedArticles::for(auth()->user())->pluck('id');

        return $query->where(function (Builder $inner) use ($visibleIds): void {
            $inner->where('subject_type', 'article')
                ->whereIn('subject_id', $visibleIds->all() ?: [0]);
        });
    }
}
