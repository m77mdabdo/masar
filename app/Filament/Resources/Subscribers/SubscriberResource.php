<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscribers;

use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Read-mostly. Subscribers are created by the public signup flow, and an editor
 * editing an email address by hand is a support problem, not a feature.
 */
class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('الجمهور');
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('مشترك');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المشتركون');
    }

    public static function getNavigationLabel(): string
    {
        return __('المشتركون');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label(__('البريد'))
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    // `$state`, not a name of our own: evaluate() resolves by
                    // name first, and an unrecognised one silently resolves
                    // from the container instead of failing (CLAUDE.md §6).
                    ->formatStateUsing(fn (SubscriberStatus $state): string => $state->label())
                    ->color(fn (SubscriberStatus $state): string => match ($state) {
                        SubscriberStatus::Confirmed => 'success',
                        SubscriberStatus::Pending => 'warning',
                        SubscriberStatus::Unsubscribed => 'gray',
                    }),
                TextColumn::make('locale')->label(__('اللغة'))->badge(),
                TextColumn::make('source')->label(__('المصدر'))->badge()->placeholder('—'),
                TextColumn::make('verified_at')->label(__('تاريخ التأكيد'))->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(__('الاشتراك'))->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))
                    ->options(fn (): array => collect(SubscriberStatus::cases())
                        ->mapWithKeys(fn (SubscriberStatus $case): array => [$case->value => $case->label()])
                        ->all())
                    ->multiple(),
                SelectFilter::make('locale')->label(__('اللغة'))->options(
                    fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all(),
                ),
                SelectFilter::make('source')->label(__('المصدر'))->options(fn (): array => Subscriber::query()
                    ->whereNotNull('source')
                    ->distinct()
                    ->pluck('source', 'source')
                    ->all()),
            ])
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    /*
                     * Streamed rather than queued: Filament's ExportBulkAction
                     * needs its own tables and a worker, and a newsletter list
                     * is small enough that the editor should just get the file.
                     */
                    BulkAction::make('export')
                        ->label(__('تصدير CSV'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => response()->streamDownload(
                            function () use ($records): void {
                                $out = fopen('php://output', 'w');

                                // BOM so Excel opens Arabic columns as UTF-8.
                                fwrite($out, "\xEF\xBB\xBF");

                                fputcsv($out, ['email', 'status', 'locale', 'source', 'verified_at', 'created_at']);

                                foreach ($records as $record) {
                                    fputcsv($out, [
                                        $record->email,
                                        $record->status,
                                        $record->locale,
                                        $record->source,
                                        $record->verified_at?->toDateString(),
                                        $record->created_at?->toDateString(),
                                    ]);
                                }

                                fclose($out);
                            },
                            'masar-subscribers-'.now()->format('Y-m-d').'.csv',
                            ['Content-Type' => 'text/csv; charset=UTF-8'],
                        )),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscribers::route('/'),
        ];
    }
}
