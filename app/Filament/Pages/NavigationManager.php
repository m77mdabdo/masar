<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Navigation\FlushNavigationCache;
use App\Actions\Navigation\SaveMenuTree;
use App\Actions\Navigation\ValidateMenuTree;
use App\Exceptions\InvalidMenuTree;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Topic;
use App\Queries\MenuTreeQuery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The navigation tree editor.
 *
 * Deliberately not a CRUD resource: managing a header means seeing the shape of
 * it. A table of rows with a `parent_id` column is technically the same data and
 * practically unusable for the person whose job this is.
 */
class NavigationManager extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'navigation';

    protected string $view = 'filament.pages.navigation-manager';

    public ?string $menuKey = null;

    public static function getNavigationLabel(): string
    {
        return 'القوائم';
    }

    public function getTitle(): string
    {
        return 'إدارة القوائم';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('navigation.manage') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->menuKey ??= Menu::query()->orderBy('id')->value('key');
    }

    /**
     * @return Collection<int, Menu>
     */
    public function menus(): Collection
    {
        return Menu::query()->orderBy('id')->get();
    }

    public function currentMenu(): ?Menu
    {
        return $this->menuKey === null
            ? null
            : Menu::query()->where('key', $this->menuKey)->first();
    }

    /**
     * The editable tree: everything, including hidden and scheduled items,
     * because this is the editor rather than the render.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $menu = $this->currentMenu();

        if ($menu === null) {
            return [];
        }

        $items = $menu->items()->with('linkable')->orderBy('sort_order')->get();

        return $this->nest($items, null);
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function nest(Collection $items, ?int $parentId): array
    {
        return $items
            ->filter(fn (MenuItem $item): bool => (int) $item->parent_id === (int) $parentId)
            ->values()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->getKey(),
                'label' => $item->label() ?? '—',
                'target' => $this->describeTarget($item),
                'is_entity_link' => $item->linkable_type !== null,
                'is_active' => (bool) $item->is_active,
                'is_mega' => (bool) $item->is_mega,
                'column_group' => $item->column_group,
                'show_desktop' => (bool) $item->show_desktop,
                'show_mobile' => (bool) $item->show_mobile,
                'scheduled' => $item->starts_at !== null || $item->ends_at !== null,
                'children' => $this->nest($items, $item->getKey()),
            ])
            ->all();
    }

    private function describeTarget(MenuItem $item): string
    {
        if ($item->linkable_type === null) {
            return $item->url ?? '—';
        }

        $linkable = $item->linkable;

        return $linkable === null
            ? 'هدف محذوف'
            : ($linkable->name ?? $linkable->title ?? $linkable->slug ?? '—');
    }

    /**
     * The rendered menu, exactly as the public site will build it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(): array
    {
        return $this->menuKey === null
            ? []
            : app(MenuTreeQuery::class)($this->menuKey, config('masar.default_locale'))->all();
    }

    /**
     * Drop handler for the tree editor.
     *
     * @param  array<int, array<string, mixed>>  $tree
     */
    public function saveTree(array $tree): void
    {
        $menu = $this->currentMenu();

        if ($menu === null) {
            return;
        }

        try {
            app(SaveMenuTree::class)($menu, $tree);

            Notification::make()->success()->title('حُفظ ترتيب القائمة')->send();
        } catch (InvalidMenuTree $e) {
            Notification::make()
                ->danger()
                ->title('ترتيب غير صالح')
                ->body(implode("\n", $e->reasons()))
                ->persistent()
                ->send();
        }

        $this->dispatch('$refresh');
    }

    public function selectMenu(string $key): void
    {
        $this->menuKey = $key;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addItemAction(),
            $this->flushCacheAction(),
        ];
    }

    /**
     * Adding an item defaults to an entity link, because that is the choice that
     * keeps working. The manual URL is available and labelled with its cost.
     */
    private function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label('أضف عنصرًا')
            ->icon('heroicon-o-plus')
            ->schema([
                TextInput::make('label_ar')->label('التسمية (عربي)')->required(),
                TextInput::make('label_en')->label('التسمية (English)')
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

                Select::make('link_mode')
                    ->label('نوع الرابط')
                    ->options([
                        'entity' => 'ارتباط بكيان (مُستحسن)',
                        'url' => 'رابط يدوي',
                    ])
                    ->default('entity')
                    ->live()
                    ->required()
                    ->helperText('الارتباط بكيان يتتبع تغيّر المسار تلقائيًا. الرابط اليدوي يتحول إلى 404 عند تغيّر مسار الوجهة.'),

                Select::make('linkable_type')
                    ->label('نوع الكيان')
                    ->options([
                        'category' => 'قسم',
                        'article' => 'مادة',
                        'topic' => 'موضوع',
                        'company' => 'شركة',
                    ])
                    ->default('category')
                    ->live()
                    ->visible(fn (Get $get): bool => $get('link_mode') === 'entity')
                    ->required(fn (Get $get): bool => $get('link_mode') === 'entity'),

                Select::make('linkable_id')
                    ->label('الوجهة')
                    ->options(fn (Get $get): array => $this->targetOptions($get('linkable_type')))
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('link_mode') === 'entity')
                    ->required(fn (Get $get): bool => $get('link_mode') === 'entity'),

                TextInput::make('url')
                    ->label('الرابط')
                    ->visible(fn (Get $get): bool => $get('link_mode') === 'url')
                    ->required(fn (Get $get): bool => $get('link_mode') === 'url')
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

                Select::make('parent_id')
                    ->label('تحت')
                    ->options(fn (): array => $this->parentOptions())
                    ->placeholder('المستوى الأول')
                    ->helperText('الحد الأقصى '.ValidateMenuTree::MAX_DEPTH.' مستويات.'),

                Toggle::make('show_desktop')->label('يظهر على سطح المكتب')->default(true),
                Toggle::make('show_mobile')->label('يظهر على الجوال')->default(true),
                Toggle::make('is_mega')->label('قائمة ضخمة (للمستوى الأول فقط)')->default(false),
                TextInput::make('column_group')->label('عمود التجميع')
                    ->helperText('لتجميع الأبناء داخل قائمة ضخمة.'),

                DateTimePicker::make('starts_at')->label('يبدأ الظهور')->seconds(false),
                DateTimePicker::make('ends_at')->label('ينتهي الظهور')->seconds(false),
            ])
            ->action(function (array $data): void {
                $menu = $this->currentMenu();

                if ($menu === null) {
                    return;
                }

                $isEntity = $data['link_mode'] === 'entity';

                $menu->items()->create([
                    'label' => array_filter([
                        'ar' => $data['label_ar'],
                        'en' => $data['label_en'] ?? null,
                    ]),
                    'parent_id' => $data['parent_id'] ?? null,
                    'linkable_type' => $isEntity ? $data['linkable_type'] : null,
                    'linkable_id' => $isEntity ? $data['linkable_id'] : null,
                    'url' => $isEntity ? null : $data['url'],
                    'sort_order' => ((int) $menu->items()->max('sort_order')) + 1,
                    'is_active' => true,
                    'show_desktop' => $data['show_desktop'] ?? true,
                    'show_mobile' => $data['show_mobile'] ?? true,
                    'is_mega' => $data['is_mega'] ?? false,
                    'column_group' => $data['column_group'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                ]);

                app(FlushNavigationCache::class)($menu);

                Notification::make()->success()->title('أُضيف العنصر')->send();
            });
    }

    private function flushCacheAction(): Action
    {
        return Action::make('flushCache')
            ->label('تفريغ ذاكرة القوائم')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->action(function (): void {
                app(FlushNavigationCache::class)($this->currentMenu());

                Notification::make()->success()->title('فُرّغت الذاكرة')->send();
            });
    }

    public function toggleItem(int $itemId): void
    {
        $item = MenuItem::query()->whereKey($itemId)->first();

        if ($item === null || (int) $item->menu_id !== (int) $this->currentMenu()?->getKey()) {
            return;
        }

        $item->forceFill(['is_active' => ! $item->is_active])->save();

        app(FlushNavigationCache::class)($this->currentMenu());
    }

    public function deleteItem(int $itemId): void
    {
        $item = MenuItem::query()->whereKey($itemId)->first();

        if ($item === null || (int) $item->menu_id !== (int) $this->currentMenu()?->getKey()) {
            return;
        }

        // Children cascade at the database level; deleting a parent from the UI
        // deletes the branch, which is what the tree shows.
        $item->delete();

        app(FlushNavigationCache::class)($this->currentMenu());

        Notification::make()->success()->title('حُذف العنصر')->send();
    }

    /**
     * @return array<int, string>
     */
    private function targetOptions(?string $type): array
    {
        return match ($type) {
            'category' => Category::query()->with('translations')->get()
                ->mapWithKeys(fn (Category $c): array => [$c->id => $c->name ?? $c->slug])->all(),
            'topic' => Topic::query()->with('translations')->get()
                ->mapWithKeys(fn (Topic $t): array => [$t->id => $t->name ?? $t->slug])->all(),
            'company' => Company::query()->with('translations')->get()
                ->mapWithKeys(fn (Company $c): array => [$c->id => $c->name ?? $c->slug])->all(),
            'article' => Article::query()->published()->orderByDesc('published_at')->limit(100)
                ->pluck('title', 'id')->all(),
            default => [],
        };
    }

    /**
     * Only items that can legally take a child — anything at the maximum depth
     * is excluded rather than offered and then rejected.
     *
     * @return array<int, string>
     */
    private function parentOptions(): array
    {
        $menu = $this->currentMenu();

        if ($menu === null) {
            return [];
        }

        $items = $menu->items()->orderBy('sort_order')->get();
        $options = [];

        foreach ($items as $item) {
            if ($this->depthOf($item, $items) >= ValidateMenuTree::MAX_DEPTH - 1) {
                continue;
            }

            $options[$item->getKey()] = str_repeat('— ', $this->depthOf($item, $items)).($item->label() ?? '—');
        }

        return $options;
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     */
    private function depthOf(MenuItem $item, Collection $items): int
    {
        $depth = 0;
        $current = $item;

        while ($current->parent_id !== null) {
            $current = $items->firstWhere('id', $current->parent_id);

            if ($current === null) {
                break;
            }

            $depth++;
        }

        return $depth;
    }
}
