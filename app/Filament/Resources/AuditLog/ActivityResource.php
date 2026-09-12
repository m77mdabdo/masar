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

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'سجل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'سجل التدقيق';
    }

    public static function getNavigationLabel(): string
    {
        return 'سجل التدقيق';
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
                TextColumn::make('created_at')->label('التوقيت')->dateTime('Y-m-d H:i')->sortable(),

                TextColumn::make('log_name')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        'article.status' => 'نقل مادة',
                        'article.published' => 'نشر',
                        default => $s ?? 'عام',
                    })
                    ->color(fn (?string $s): string => $s === 'article.published' ? 'success' : 'gray'),

                TextColumn::make('description')->label('الوصف')->wrap()->limit(60),

                TextColumn::make('causer.name')->label('المستخدم')->placeholder('النظام'),

                TextColumn::make('subject_type')
                    ->label('الكيان')
                    ->badge()
                    ->formatStateUsing(fn (?string $s): string => match ($s) {
                        'article' => 'مادة',
                        'company' => 'شركة',
                        'menu_item' => 'عنصر قائمة',
                        'setting' => 'إعداد',
                        default => $s ?? '—',
                    }),

                TextColumn::make('properties')
                    ->label('التفاصيل')
                    ->wrap()
                    ->limit(80)
                    ->formatStateUsing(function ($state): string {
                        $props = $state instanceof Collection ? $state->all() : (array) $state;

                        if (isset($props['from'], $props['to'])) {
                            $line = "{$props['from']} ← {$props['to']}";

                            return filled($props['note'] ?? null) ? $line.' · '.$props['note'] : $line;
                        }

                        return json_encode($props, JSON_UNESCAPED_UNICODE) ?: '';
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('النوع')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->all()),

                SelectFilter::make('causer_id')
                    ->label('المستخدم')
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('subject_type')
                    ->label('الكيان')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->all()),

                Filter::make('logged_between')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
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
