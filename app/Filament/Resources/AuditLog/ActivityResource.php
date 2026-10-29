<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLog;

use App\Filament\Resources\AuditLog\Pages\ListActivities;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only view of the activity log.
 *
 * Nothing here is editable or deletable — an audit trail that can be edited from
 * the panel it audits is not an audit trail.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('النظام');
    }

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('سجل');
    }

    public static function getPluralModelLabel(): string
    {
        return __('سجل التدقيق');
    }

    public static function getNavigationLabel(): string
    {
        return __('سجل التدقيق');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('audit.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('التوقيت'))->dateTime('Y-m-d H:i')->sortable(),

                TextColumn::make('log_name')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'article.status' => __('نقل مادة'),
                        'article.published' => __('نشر'),
                        default => $state ?? __('عام'),
                    })
                    ->color(fn (?string $state): string => $state === 'article.published' ? 'success' : 'gray'),

                TextColumn::make('description')->label(__('الوصف'))->wrap()->limit(60)
                    ->formatStateUsing(fn (Activity $record): string => \App\Support\AuditDescription::present($record)),

                TextColumn::make('causer.name')->label(__('المستخدم'))->placeholder(__('النظام')),

                TextColumn::make('subject_type')
                    ->label(__('الكيان'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'article' => __('مادة'),
                        'company' => __('شركة'),
                        'menu_item' => __('عنصر قائمة'),
                        'setting' => __('إعداد'),
                        default => $state ?? '—',
                    }),

                TextColumn::make('properties')
                    ->label(__('التفاصيل'))
                    ->wrap()
                    ->limit(80)
                    ->formatStateUsing(function ($state): string {
                        $props = $state instanceof Collection ? $state->all() : (array) $state;

                        $transition = \App\Support\AuditDescription::transition($props);
                        if ($transition !== null) {
                            return $transition;
                        }

                        return json_encode($props, JSON_UNESCAPED_UNICODE) ?: '';
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('النوع'))
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->all()),

                SelectFilter::make('causer_id')
                    ->label(__('المستخدم'))
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('subject_type')
                    ->label(__('الكيان'))
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->all()),

                Filter::make('logged_between')
                    ->label(__('الفترة'))
                    ->schema([
                        DatePicker::make('from')->label(__('من')),
                        DatePicker::make('until')->label(__('إلى')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $b, $d): Builder => $b->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $b, $d): Builder => $b->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
