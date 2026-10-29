<?php

declare(strict_types=1);

namespace App\Filament\Resources\Intelligence;

use App\Actions\Intelligence\ClearDuplicateFlag;
use App\Actions\Intelligence\CreateDraftFromIntelligenceItem;
use App\Actions\Intelligence\TriageIntelligenceItem;
use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Enums\ReviewState;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Intelligence\Pages\ListIntelligenceItems;
use App\Models\IntelligenceItem;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The editorial inbox.
 *
 * Built on IntelligenceItem and not on SourceItem, deliberately. SourceItem
 * holds the publisher's own prose, and CLAUDE.md §5 says that text is internal
 * for its whole life — never rendered publicly, and never placed in a Filament
 * field an editor can copy from. IntelligenceItem carries only facts about the
 * publication plus our own writing, which is exactly what a triage queue needs
 * and the reason the two tables are separate.
 *
 * There is no create and no edit. An editor triages what the pipeline found;
 * they do not type rows into it.
 */
class IntelligenceItemResource extends Resource
{
    protected static ?string $model = IntelligenceItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('المحتوى');
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('عنصر رصد');
    }

    public static function getPluralModelLabel(): string
    {
        return __('صندوق الرصد');
    }

    public static function getNavigationLabel(): string
    {
        return __('صندوق الرصد');
    }

    /** The count an editor actually cares about: what is still untriaged. */
    public static function getNavigationBadge(): ?string
    {
        $queued = IntelligenceItem::query()->where('review_state', ReviewState::New)->count();

        return $queued > 0 ? (string) $queued : null;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('intelligence.triage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            // The queue order: most important first, then most recent. Sorted by
            // the *live* score — base plus recency against NOW() — because a
            // stored score freezes an item's age at ingest and the ranking
            // stops meaning anything within a day.
            ->defaultSort(fn (Builder $query): Builder => $query->orderByLiveImportance())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['source', 'duplicateOf', 'reviewer']))
            ->columns([
                TextColumn::make('title')
                    ->label(__('العنوان'))
                    ->wrap()
                    ->searchable()
                    ->description(fn (IntelligenceItem $record): ?string => $record->summary)
                    // Opens the publisher's page rather than reproducing it here.
                    ->url(fn (IntelligenceItem $record): ?string => $record->url, shouldOpenInNewTab: true),

                TextColumn::make('source.name')
                    ->label(__('المصدر'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('importance')
                    ->label(__('الأهمية'))
                    ->badge()
                    ->sortable()
                    // The live score, not the stored one. The stored value is
                    // kept for audit — it is what we thought at ingest — but it
                    // is not what should rank the queue today.
                    ->state(fn (IntelligenceItem $record): int => $record->liveImportance())
                    ->tooltip(fn (IntelligenceItem $record): string => __('عند الرصد: :value', ['value' => $record->importance]))
                    ->color(fn (int $state): string => match (true) {
                        $state >= 70 => 'danger',
                        $state >= 40 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('document_type')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (?DocumentType $state): string => $state?->label() ?? '—'),

                TextColumn::make('classification_path')
                    ->label(__('التصنيف'))
                    ->badge()
                    ->formatStateUsing(fn (?ClassificationPath $state): string => $state?->label() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('review_state')
                    ->label(__('الحالة'))
                    ->badge()
                    ->formatStateUsing(fn (ReviewState $state): string => $state->label())
                    ->color(fn (ReviewState $state): string => $state->colour()),

                // Visible by default, not behind a toggle. The title stage is
                // known-weak, so an editor needs to see what an item was
                // flagged against in order to judge the flag at all.
                TextColumn::make('duplicateOf.title')
                    ->label(__('مكرر عن'))
                    ->placeholder('—')
                    ->wrap()
                    ->color('warning')
                    ->description(fn (IntelligenceItem $record): ?string => $record->duplicate_stage === null
                        ? null
                        : __('مطابقة عبر: :stage', ['stage' => $record->duplicate_stage->label()]))
                    ->url(fn (IntelligenceItem $record): ?string => $record->duplicateOf?->url, shouldOpenInNewTab: true),

                TextColumn::make('published_at')
                    ->label(__('النشر'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label(__('راجعه'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label(__('تاريخ المراجعة'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_state')
                    ->label(__('الحالة'))
                    ->options(ReviewState::options())
                    ->multiple()
                    // The inbox opens as a queue to clear, not an archive.
                    ->default([ReviewState::New->value]),

                SelectFilter::make('source_id')
                    ->label(__('المصدر'))
                    ->options(fn (): array => Source::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple(),

                SelectFilter::make('document_type')
                    ->label(__('النوع'))
                    ->options(fn (): array => collect(DocumentType::cases())
                        ->mapWithKeys(fn (DocumentType $case): array => [$case->value => $case->label()])
                        ->all()),

                TernaryFilter::make('duplicate_of_id')
                    ->label(__('المكرر'))
                    ->placeholder(__('الكل'))
                    ->trueLabel(__('المكرر فقط'))
                    ->falseLabel(__('بلا تكرار'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('duplicate_of_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('duplicate_of_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                Action::make('createDraft')
                    ->label(__('أنشئ مسودة'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    // Once an item has a draft it has one; the action is not a
                    // way to create a second article for the same story.
                    ->visible(fn (IntelligenceItem $record): bool => $record->article_id === null)
                    ->action(function (IntelligenceItem $record) {
                        $article = app(CreateDraftFromIntelligenceItem::class)($record, auth()->user());

                        Notification::make()
                            ->title(__('أُنشئت مسودة'))
                            ->body(__('المصدر مرفق كمرجع. العنوان والنص من كتابتك.'))
                            ->success()
                            ->send();

                        return redirect(ArticleResource::getUrl('edit', ['record' => $article]));
                    }),
                self::triageAction('approve', ReviewState::Approved, __('اعتماد'), Heroicon::OutlinedCheckCircle, 'success'),
                self::triageAction('save', ReviewState::Saved, __('حفظ لاحقًا'), Heroicon::OutlinedBookmark, 'warning'),
                ActionGroup::make([
                    self::triageAction('reject', ReviewState::Rejected, __('رفض'), Heroicon::OutlinedXCircle, 'danger'),
                    self::triageAction('ignore', ReviewState::Ignored, __('تجاهل'), Heroicon::OutlinedEyeSlash, 'gray'),
                    // The recovery half of the 30-day window: a dismissal made
                    // by mistake at 9am is undoable for the rest of the month.
                    self::triageAction('restore', ReviewState::New, __('إعادة إلى الصندوق'), Heroicon::OutlinedArrowUturnLeft, 'info')
                        ->visible(fn (IntelligenceItem $record): bool => $record->review_state->isResolved()),
                    Action::make('notDuplicate')
                        ->label(__('ليس مكررًا'))
                        ->icon(Heroicon::OutlinedScissors)
                        ->color('warning')
                        ->visible(fn (IntelligenceItem $record): bool => $record->duplicate_of_id !== null)
                        ->action(function (IntelligenceItem $record): void {
                            app(ClearDuplicateFlag::class)($record, auth()->user());
                        }),
                    Action::make('open')
                        ->label(__('فتح المصدر'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (IntelligenceItem $record): ?string => $record->url, shouldOpenInNewTab: true),
                ])->label(__('المزيد')),
            ])
            ->toolbarActions([
                self::bulkTriage('bulkReject', ReviewState::Rejected, __('رفض المحدد'), 'danger'),
                self::bulkTriage('bulkIgnore', ReviewState::Ignored, __('تجاهل المحدد'), 'gray'),
            ])
            ->emptyStateHeading(__('لا شيء في الصندوق'))
            ->emptyStateDescription(__('كل ما رصدته المصادر جرت مراجعته.'));
    }

    private static function triageAction(
        string $name,
        ReviewState $state,
        string $label,
        BackedEnum $icon,
        string $colour,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($colour)
            ->requiresConfirmation($state->isDismissal())
            ->visible(fn (IntelligenceItem $record): bool => $record->review_state !== $state)
            ->action(function (IntelligenceItem $record) use ($state): void {
                app(TriageIntelligenceItem::class)($record, $state, auth()->user());
            });
    }

    private static function bulkTriage(string $name, ReviewState $state, string $label, string $colour): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->color($colour)
            ->icon(Heroicon::OutlinedXCircle)
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($state): void {
                $triage = app(TriageIntelligenceItem::class);
                $actor = auth()->user();

                foreach ($records as $record) {
                    $triage($record, $state, $actor);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntelligenceItems::route('/'),
        ];
    }
}
