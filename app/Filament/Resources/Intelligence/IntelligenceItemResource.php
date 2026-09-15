<?php

declare(strict_types=1);

namespace App\Filament\Resources\Intelligence;

use App\Actions\Intelligence\TriageIntelligenceItem;
use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Enums\ReviewState;
use App\Filament\Resources\Intelligence\Pages\ListIntelligenceItems;
use App\Models\IntelligenceItem;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
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

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'عنصر رصد';
    }

    public static function getPluralModelLabel(): string
    {
        return 'صندوق الرصد';
    }

    public static function getNavigationLabel(): string
    {
        return 'صندوق الرصد';
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
            // The queue order: most important first, then most recent. Matches
            // IntelligenceItem::scopeQueued so the surface and the model agree.
            ->defaultSort('importance', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['source', 'duplicateOf', 'reviewer']))
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->wrap()
                    ->searchable()
                    ->description(fn (IntelligenceItem $record): ?string => $record->summary)
                    // Opens the publisher's page rather than reproducing it here.
                    ->url(fn (IntelligenceItem $record): ?string => $record->url, shouldOpenInNewTab: true),

                TextColumn::make('source.name')
                    ->label('المصدر')
                    ->badge()
                    ->sortable(),

                TextColumn::make('importance')
                    ->label('الأهمية')
                    ->badge()
                    ->sortable()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'danger',
                        $state >= 40 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('document_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?DocumentType $state): string => $state?->label() ?? '—'),

                TextColumn::make('classification_path')
                    ->label('التصنيف')
                    ->badge()
                    ->formatStateUsing(fn (?ClassificationPath $state): string => $state?->label() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('review_state')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (ReviewState $state): string => $state->label())
                    ->color(fn (ReviewState $state): string => $state->colour()),

                TextColumn::make('duplicateOf.title')
                    ->label('مكرر عن')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label('النشر')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label('راجعه')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label('تاريخ المراجعة')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_state')
                    ->label('الحالة')
                    ->options(ReviewState::options())
                    ->multiple()
                    // The inbox opens as a queue to clear, not an archive.
                    ->default([ReviewState::New->value]),

                SelectFilter::make('source_id')
                    ->label('المصدر')
                    ->options(fn (): array => Source::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple(),

                SelectFilter::make('document_type')
                    ->label('النوع')
                    ->options(fn (): array => collect(DocumentType::cases())
                        ->mapWithKeys(fn (DocumentType $case): array => [$case->value => $case->label()])
                        ->all()),

                TernaryFilter::make('duplicate_of_id')
                    ->label('المكرر')
                    ->placeholder('الكل')
                    ->trueLabel('المكرر فقط')
                    ->falseLabel('بلا تكرار')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('duplicate_of_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('duplicate_of_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                self::triageAction('approve', ReviewState::Approved, 'اعتماد', Heroicon::OutlinedCheckCircle, 'success'),
                self::triageAction('save', ReviewState::Saved, 'حفظ لاحقًا', Heroicon::OutlinedBookmark, 'warning'),
                ActionGroup::make([
                    self::triageAction('reject', ReviewState::Rejected, 'رفض', Heroicon::OutlinedXCircle, 'danger'),
                    self::triageAction('ignore', ReviewState::Ignored, 'تجاهل', Heroicon::OutlinedEyeSlash, 'gray'),
                    // The recovery half of the 30-day window: a dismissal made
                    // by mistake at 9am is undoable for the rest of the month.
                    self::triageAction('restore', ReviewState::New, 'إعادة إلى الصندوق', Heroicon::OutlinedArrowUturnLeft, 'info')
                        ->visible(fn (IntelligenceItem $record): bool => $record->review_state->isResolved()),
                    Action::make('open')
                        ->label('فتح المصدر')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (IntelligenceItem $record): ?string => $record->url, shouldOpenInNewTab: true),
                ])->label('المزيد'),
            ])
            ->toolbarActions([
                self::bulkTriage('bulkReject', ReviewState::Rejected, 'رفض المحدد', 'danger'),
                self::bulkTriage('bulkIgnore', ReviewState::Ignored, 'تجاهل المحدد', 'gray'),
            ])
            ->emptyStateHeading('لا شيء في الصندوق')
            ->emptyStateDescription('كل ما رصدته المصادر جرت مراجعته.');
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
