<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('النظام');
    }

    protected static ?int $navigationSort = 2;

    /**
     * Permission areas, in the order an editor thinks about them.
     *
     * @var array<string, string>
     */
    private const AREAS = [
        'article' => 'المواد',
        'navigation' => 'التنقل',
        'homepage' => 'الصفحة الرئيسية',
        'settings' => 'الإعدادات',
        'users' => 'المستخدمون',
        'roles' => 'الأدوار',
        'intelligence' => 'الرصد',
        'audit' => 'سجل التدقيق',
    ];

    public static function getModelLabel(): string
    {
        return __('دور');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الأدوار');
    }

    public static function getNavigationLabel(): string
    {
        return __('الأدوار');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('الاسم'))
                ->required()
                ->unique(ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),

            // Grouped by area rather than one flat list of seventeen checkboxes:
            // "what can an editor do with articles" is the question being asked.
            ...collect(self::AREAS)
                ->map(fn (string $label, string $area) => Section::make(__($label))
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship(
                                'permissions',
                                'name',
                                fn (Builder $query) => $query->where('name', 'like', $area.'.%'),
                            )
                            ->options(fn (): array => Permission::query()
                                ->where('name', 'like', $area.'.%')
                                ->pluck('name', 'id')
                                ->all())
                            ->descriptions(fn (): array => Permission::query()
                                ->where('name', 'like', $area.'.%')
                                ->pluck('name', 'id')
                                ->map(fn (string $n): string => self::describe($n))
                                ->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ])
                    ->collapsible()
                    ->columnSpanFull())
                ->values()
                ->all(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['permissions', 'users']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->searchable()->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('permissions_count')->label(__('عدد الصلاحيات'))->numeric(),
                TextColumn::make('users_count')->label(__('عدد المستخدمين'))->numeric(),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
    }

    private static function describe(string $permission): string
    {
        return match ($permission) {
            'article.view' => __('عرض المواد'),
            'article.create' => __('إنشاء مادة'),
            'article.update.own' => __('تحرير موادّه فقط'),
            'article.update.any' => __('تحرير أي مادة'),
            'article.delete' => __('حذف المواد'),
            'article.transition' => __('نقل المادة في مسار التحرير'),
            'article.publish' => __('النشر (يتطلب تحققًا ثنائيًا)'),
            'article.factcheck' => __('تدقيق المعلومات'),
            'article.seo' => __('تحسين محركات البحث'),
            default => $permission,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
