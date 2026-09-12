<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Tables;

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Models\Article;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /*
             * The eager-load set is the whole reason this listing is not an N+1.
             * category.translations is needed for the name; author is the byline.
             * Blocks, sources and revisions are never loaded here.
             */
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'category:id,slug,color',
                'category.translations',
                'author:id,name',
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(80)
                    ->description(fn (Article $record): ?string => $record->subtitle),

                TextColumn::make('category.name')
                    ->label('القسم')
                    ->badge()
                    ->getStateUsing(fn (Article $record): ?string => $record->category?->name)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('category_id', $direction)),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state): string => $state->label())
                    ->color(fn (ArticleStatus $state): string => match ($state) {
                        ArticleStatus::Published => 'success',
                        ArticleStatus::Scheduled => 'warning',
                        ArticleStatus::Ready => 'info',
                        ArticleStatus::NeedsRevision, ArticleStatus::Rejected => 'danger',
                        ArticleStatus::Archived, ArticleStatus::OnHold => 'gray',
                        default => 'primary',
                    })
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label('الكاتب')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('views_count')
                    ->label('المشاهدات')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_featured')
                    ->label('مميزة')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_sponsored')
                    ->label('مدفوعة')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ArticleStatus::options())
                    ->multiple(),

                SelectFilter::make('category')
                    ->label('القسم')
                    ->relationship('category', 'slug')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('author')
                    ->label('الكاتب')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('locale')
                    ->label('اللغة')
                    ->options(fn (): array => collect(config('masar.locales'))
                        ->map(fn (array $l): string => $l['name'])
                        ->all()),

                SelectFilter::make('content_type')
                    ->label('نوع المحتوى')
                    ->options(ContentType::options())
                    ->multiple(),

                TernaryFilter::make('is_featured')->label('مميزة'),
                TernaryFilter::make('is_sponsored')->label('مدفوعة'),

                Filter::make('published_between')
                    ->label('تاريخ النشر')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('published_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('published_at', '<=', $d))),

                /*
                 * The queue an editor-in-chief actually works from: everything
                 * that has been declared finished but cannot go out.
                 *
                 * The gate is PHP, not SQL, so this narrows in the database to
                 * the only statuses that can be blocked and then filters in
                 * memory. That is a deliberate trade — see the task report.
                 */
                Filter::make('blocked_by_gate')
                    ->label('محجوبة ببوابة النشر')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [
                            ArticleStatus::Ready->value,
                            ArticleStatus::Scheduled->value,
                            ArticleStatus::Seo->value,
                        ])
                        ->whereIn('id', Article::query()
                            ->whereIn('status', [
                                ArticleStatus::Ready->value,
                                ArticleStatus::Scheduled->value,
                                ArticleStatus::Seo->value,
                            ])
                            ->with('sources')
                            ->get()
                            ->filter(fn (Article $a): bool => $a->isPublishable() !== [])
                            ->pluck('id')
                            ->all() ?: [0])),
            ])
            ->recordActions([
                EditAction::make()->label('تحرير'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('feature')
                        ->label('تمييز')
                        ->icon('heroicon-o-star')
                        ->action(fn (Collection $records) => $records->each->forceFill(['is_featured' => true])->each->save())
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unfeature')
                        ->label('إلغاء التمييز')
                        ->icon('heroicon-o-star')
                        ->action(fn (Collection $records) => $records->each->forceFill(['is_featured' => false])->each->save())
                        ->deselectRecordsAfterCompletion(),

                    /*
                     * Archiving is a status change, so it goes through the
                     * Action like every other one — and silently skips records
                     * whose current status has no legal edge to `archived`.
                     *
                     * There is deliberately no bulk publish. Publishing is a
                     * per-article decision with a gate behind it.
                     */
                    BulkAction::make('archive')
                        ->label('أرشفة')
                        ->icon('heroicon-o-archive-box')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $transition = app(TransitionArticleStatus::class);
                            $done = 0;

                            foreach ($records as $record) {
                                if (! $record->status->canTransitionTo(ArticleStatus::Archived)) {
                                    continue;
                                }

                                try {
                                    $transition($record, ArticleStatus::Archived, auth()->user(), 'أرشفة جماعية');
                                    $done++;
                                } catch (\Throwable) {
                                    // Reported in the summary below rather than
                                    // aborting the whole batch.
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("تمت أرشفة {$done} من {$records->count()}")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
