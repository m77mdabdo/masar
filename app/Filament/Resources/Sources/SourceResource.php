<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sources;

use App\Enums\SourceLegalMode;
use App\Enums\SourceType;
use App\Filament\Resources\Sources\Pages\CreateSource;
use App\Filament\Resources\Sources\Pages\EditSource;
use App\Filament\Resources\Sources\Pages\ListSources;
use App\Jobs\CheckSourceJob;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The sources we watch, and — more importantly — whether they are still working.
 *
 * The table is built around health rather than configuration. A monitoring
 * system that silently stops monitoring is the worst failure available to it,
 * so "which of these has quietly died" must be answerable at a glance and not
 * by opening six records.
 */
class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static string|UnitEnum|null $navigationGroup = 'الرصد';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مصدر';

    protected static ?string $pluralModelLabel = 'المصادر';

    public static function getNavigationLabel(): string
    {
        return 'المصادر';
    }

    /**
     * Failing sources in the navigation badge. The count is the point: an
     * editor should not have to go looking to find out something broke.
     */
    public static function getNavigationBadge(): ?string
    {
        $broken = Source::query()->where('consecutive_failures', '>', 0)->count();

        return $broken > 0 ? (string) $broken : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Configuring what we watch is an administrative act, not an editorial one:
     * a source's trust level feeds the ranking every editor sees, and its legal
     * mode decides what we are allowed to keep.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('intelligence.configure') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required()->columnSpanFull(),

            TextInput::make('url')->label('رابط الموقع')->url()->required()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TextInput::make('feed_url')->label('رابط التغذية')->url()
                ->helperText('اتركه فارغًا إذا كان الرابط أعلاه هو التغذية نفسها.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            Select::make('type')->label('النوع')->options(SourceType::options())->required()->native(false),

            TextInput::make('category')->label('تصنيف المصدر')
                ->helperText('اختياري. يُستخدم كقاعدة تصنيف احتياطية حين لا تطابق أي كلمة مفتاحية.'),

            Select::make('locale')->label('اللغة')
                ->options(['ar' => 'العربية', 'en' => 'English'])->default('ar')->required()->native(false),

            Select::make('trust_level')->label('مستوى الثقة')
                ->options([1 => '1 — ضعيف', 2 => '2', 3 => '3 — متوسط', 4 => '4', 5 => '5 — جهة رسمية'])
                ->default(3)->required()->native(false)
                ->helperText('حكم على الناشر، لا على أي خبر بعينه. يدخل في ترتيب الأهمية.'),

            TextInput::make('poll_frequency_minutes')->label('كل كم دقيقة')
                ->numeric()->minValue(5)->maxValue(1440)->default(30)->required(),

            Select::make('legal_mode')->label('ما يُسمح بحفظه')
                ->options(SourceLegalMode::options())
                ->default(SourceLegalMode::Metadata->value)->required()->native(false)
                ->helperText('الوضع الافتراضي لا يحفظ نص الناشر إطلاقًا. لا ترفعه إلا بإذن صريح.'),

            Toggle::make('is_active')->label('نشط')->default(true),

            KeyValue::make('parser_config')->label('إعدادات المحلّل')
                ->helperText('لمصادر الواجهات البرمجية: items_path ومسارات الحقول.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('المصدر')->searchable()
                    ->description(fn (Source $record): string => $record->pollUrl()),

                // Health first, because it is the question this table exists to
                // answer.
                TextColumn::make('health')->label('الحالة')
                    ->state(fn (Source $record): string => match ($record->healthState()) {
                        'healthy' => 'سليم',
                        'quiet' => 'صامت',
                        'failing' => 'يفشل',
                        'disabled' => 'معطّل تلقائيًا',
                        'paused' => 'موقوف',
                        default => 'لم يُفحص',
                    })
                    ->badge()
                    ->color(fn (Source $record): string => match ($record->healthState()) {
                        'healthy' => 'success',
                        'quiet' => 'warning',
                        'failing', 'disabled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('last_success_at')->label('آخر نجاح')
                    ->since()->placeholder('لا يوجد')->sortable(),

                TextColumn::make('consecutive_failures')->label('إخفاقات متتالية')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'danger')
                    ->formatStateUsing(fn (int $state, Source $record): string => $state.'/'.$record->failureThreshold()),

                // The measure that catches a feed that has moved: answering
                // 200 and producing nothing looks healthy by every other test.
                TextColumn::make('recent_items')->label('آخر ٧ أيام')
                    ->state(fn (Source $record): int => $record->intelligenceItems()
                        ->where('detected_at', '>=', now()->subDays(7))->count())
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'warning' : 'success'),

                TextColumn::make('trust_level')->label('الثقة')->badge()->sortable(),

                TextColumn::make('error_message')->label('آخر خطأ')
                    ->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('consecutive_failures', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label('نشط'),
                SelectFilter::make('type')->label('النوع')->options(SourceType::options()),
                // The one an editor actually reaches for.
                Filter::make('failing')
                    ->label('يفشل حاليًا')
                    ->query(fn ($query) => $query->where('consecutive_failures', '>', 0)),
            ])
            ->recordActions([
                EditAction::make()->label('تحرير'),

                // Re-enabling clears the streak: leaving it at the threshold
                // would disable the source again on its next failure, which
                // looks like the fix did not work.
                Action::make('reactivate')
                    ->label('إعادة تفعيل')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Source $record): bool => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (Source $record): void {
                        $record->forceFill([
                            'is_active' => true,
                            'consecutive_failures' => 0,
                            'error_message' => null,
                            'last_checked_at' => null,
                        ])->save();

                        Notification::make()->success()->title('أُعيد تفعيل المصدر')->send();
                    }),

                Action::make('check')
                    ->label('افحص الآن')
                    ->icon(Heroicon::OutlinedBolt)
                    ->action(function (Source $record): void {
                        CheckSourceJob::dispatchSync($record->getKey());

                        $record->refresh();

                        Notification::make()
                            ->title($record->consecutive_failures === 0 ? 'تم الفحص' : 'فشل الفحص')
                            ->body($record->error_message ?: 'المصدر يستجيب.')
                            ->status($record->consecutive_failures === 0 ? 'success' : 'danger')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSources::route('/'),
            'create' => CreateSource::route('/create'),
            'edit' => EditSource::route('/{record}/edit'),
        ];
    }
}
