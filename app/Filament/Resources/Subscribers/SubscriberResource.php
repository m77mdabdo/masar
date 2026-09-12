<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscribers;

use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
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

    protected static string|UnitEnum|null $navigationGroup = 'الجمهور';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'مشترك';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المشتركون';
    }

    public static function getNavigationLabel(): string
    {
        return 'المشتركون';
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
                    ->label('البريد')
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $s): string => match ($s) {
                        'confirmed' => 'مؤكَّد',
                        'pending' => 'بانتظار التأكيد',
                        'unsubscribed' => 'ألغى الاشتراك',
                        default => $s,
                    })
                    ->color(fn (string $s): string => match ($s) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('locale')->label('اللغة')->badge(),
                TextColumn::make('source')->label('المصدر')->badge()->placeholder('—'),
                TextColumn::make('verified_at')->label('تاريخ التأكيد')->dateTime('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('الاشتراك')->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    'pending' => 'بانتظار التأكيد',
                    'confirmed' => 'مؤكَّد',
                    'unsubscribed' => 'ألغى الاشتراك',
                ])->multiple(),
                SelectFilter::make('locale')->label('اللغة')->options(
                    fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all(),
                ),
                SelectFilter::make('source')->label('المصدر')->options(fn (): array => Subscriber::query()
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
                        ->label('تصدير CSV')
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
