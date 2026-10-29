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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect(['editions_published' => \App\Models\Article::query()->from('articles as editions')
                ->selectRaw('count(*)')->whereColumn('editions.translation_group_id', 'articles.translation_group_id')
                ->where('editions.status', \App\Enums\ArticleStatus::Published->value)->whereNull('editions.deleted_at')])->with([
                'category:id,slug,color',
                'category.translations',
                'author:id,name',
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('العنوان'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(80)
                    ->description(fn (Article $record): ?string => $record->subtitle),

                TextColumn::make('category.name')
                    ->label(__('القسم'))
                    ->badge()
                    ->getStateUsing(fn (Article $record): ?string => $record->category?->name)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('category_id', $direction)),

                TextColumn::make('status')
                    ->label(__('الحالة'))
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
                    ->label(__('الكاتب'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label(__('تاريخ النشر'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('views_count')
                    ->label(__('المشاهدات'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('locale')
                    ->label(__('admin.translation.language'))
                    ->formatStateUsing(fn (?string $state): string => \App\Support\Locales::name((string) $state))
                    ->toggleable(),

                // How many of the six editions carry this story, published. A
                // subquery in the list query, so it costs nothing per row.
                TextColumn::make('editions_published')
                    ->label(__('النسخ المنشورة'))
                    ->state(fn (\App\Models\Article $record): string => ((int) $record->editions_published).'/'.count(\App\Support\Locales::codes()))
                    ->badge()
                    ->color(fn (\App\Models\Article $record): string => (int) $record->editions_published >= count(\App\Support\Locales::codes()) ? 'success' : 'warning')
                    ->toggleable(),

                // The stored status only. "Outdated" needs the source's blocks,
                // which a list must not load; it is shown on the article itself.
                TextColumn::make('translation_status')
                    ->label(__('admin.translation.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->color(fn ($state): string => match ($state) {
                        \App\Enums\TranslationStatus::Reviewed => 'success',
                        \App\Enums\TranslationStatus::Failed => 'danger',
                        \App\Enums\TranslationStatus::NeedsReview, \App\Enums\TranslationStatus::AwaitingTranslator => 'warning',
                        default => 'gray',
                    })
                    ->placeholder(__('admin.translation.original'))
                    ->toggleable(),

                IconColumn::make('is_featured')
                    ->label(__('مميزة'))
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_sponsored')
                    ->label(__('مدفوعة'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('الحالة'))
                    ->options(ArticleStatus::options())
                    ->multiple(),

                SelectFilter::make('category')
                    ->label(__('القسم'))
                    ->relationship('category', 'slug')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('author')
                    ->label(__('الكاتب'))
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('locale')
                    ->label(__('اللغة'))
                    ->options(fn (): array => collect(\App\Support\Locales::codes())
                        ->mapWithKeys(fn (string $c): array => [$c => \App\Support\Locales::name($c)])
                        ->all()),

                SelectFilter::make('translation_status')
                    ->label(__('admin.translation.status'))
                    ->options(\App\Enums\TranslationStatus::options()),

                SelectFilter::make('content_type')
                    ->label(__('نوع المحتوى'))
                    ->options(ContentType::options())
                    ->multiple(),

                TernaryFilter::make('is_featured')->label(__('مميزة')),
                TernaryFilter::make('is_sponsored')->label(__('مدفوعة')),

                Filter::make('published_between')
                    ->label(__('تاريخ النشر'))
                    ->schema([
                        DatePicker::make('from')->label(__('من')),
                        DatePicker::make('until')->label(__('إلى')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('published_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d): Builder => $q->whereDate('published_at', '<=', $d))),

                /*
                 * The queue an editor-in-chief actually works from.
                 *
                 * Pure SQL against the denormalised count. NULL is excluded on
                 * purpose: it means "never evaluated", not "blocked", and
                 * masar:recount is what turns NULLs into real numbers.
                 */
                Filter::make('blocked_by_gate')
                    ->label(__('محجوبة ببوابة النشر'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('gate_failures_count', '>', 0)
                        ->whereIn('status', [
                            ArticleStatus::Ready->value,
                            ArticleStatus::Scheduled->value,
                            ArticleStatus::Seo->value,
                        ])),
            ])
            ->recordActions([
                EditAction::make()->label(__('تحرير')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('feature')
                        ->label(__('تمييز'))
                        ->icon('heroicon-o-star')
                        ->action(fn (Collection $records) => $records->each->forceFill(['is_featured' => true])->each->save())
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unfeature')
                        ->label(__('إلغاء التمييز'))
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
                        ->label(__('أرشفة'))
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
                                    $transition($record, ArticleStatus::Archived, auth()->user(), __('أرشفة جماعية'));
                                    $done++;
                                } catch (\Throwable) {
                                    // Reported in the summary below rather than
                                    // aborting the whole batch.
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title(__('تمت أرشفة :done من :total', ['done' => $done, 'total' => $records->count()]))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
