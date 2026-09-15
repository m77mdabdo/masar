<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Homepage\ActivateLayout;
use App\Actions\Homepage\DuplicateLayout;
use App\Enums\HomepageSectionType;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Models\HomepageSection;
use App\Queries\ComposeHomepage;
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
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * The homepage composer.
 *
 * This is where editorial judgement is expressed, so it shows the *result* of
 * every choice rather than only the configuration that produced it: an editor
 * setting "latest in Saudi, 5 items" needs to see which five.
 *
 * All resolution goes through ComposeHomepage, the same object the public page
 * will use in TASK 05. A preview that composes differently from the live page is
 * confidently wrong, which is worse than absent.
 */
class HomepageComposer extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'homepage';

    protected string $view = 'filament.pages.homepage-composer';

    public ?int $layoutId = null;

    public static function getNavigationLabel(): string
    {
        return 'الصفحة الرئيسية';
    }

    public function getTitle(): string
    {
        return 'تركيب الصفحة الرئيسية';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('homepage.manage') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->layoutId ??= HomepageLayout::query()->where('is_active', true)->value('id')
            ?? HomepageLayout::query()->orderBy('id')->value('id');
    }

    /**
     * @return Collection<int, HomepageLayout>
     */
    public function layouts(): Collection
    {
        return HomepageLayout::query()->withCount('sections')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function currentLayout(): ?HomepageLayout
    {
        return $this->layoutId === null ? null : HomepageLayout::query()->find($this->layoutId);
    }

    public function selectLayout(int $id): void
    {
        $this->layoutId = $id;
    }

    /**
     * The composed page, resolved exactly as the public site will.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function composed(): Collection
    {
        $layout = $this->currentLayout();

        return $layout === null
            ? collect()
            : app(ComposeHomepage::class)($layout, config('masar.default_locale'));
    }

    /**
     * @return Collection<int, HomepageSection>
     */
    public function sections(): Collection
    {
        $layout = $this->currentLayout();

        return $layout === null ? collect() : $layout->sections()->orderBy('sort_order')->get();
    }

    /**
     * Drag-reorder handler.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderSections(array $orderedIds): void
    {
        $layout = $this->currentLayout();

        if ($layout === null) {
            return;
        }

        $owned = $layout->sections()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $position = 1;

        foreach ($orderedIds as $id) {
            $id = (int) $id;

            // Ignore anything that is not ours rather than trusting the payload.
            if (! in_array($id, $owned, true)) {
                continue;
            }

            HomepageSection::query()->whereKey($id)->update(['sort_order' => $position++]);
        }

        Cache::tags('homepage')->flush();

        Notification::make()->success()->title('أُعيد ترتيب الأقسام')->send();
    }

    /**
     * Keyboard path to the same reordering the drag handles do.
     *
     * HTML5 drag-and-drop cannot be operated from a keyboard at all, so for the
     * life of this page an editor who does not use a pointer could hide a
     * section and delete one but not move one. §5 makes keyboard navigation a
     * floor, and TASK 04's whole point is that the owner changes the site
     * without a developer — which has to include an owner using a keyboard.
     */
    public function moveSection(int $sectionId, int $direction): void
    {
        $section = $this->ownedSection($sectionId);

        if ($section === null || ! in_array($direction, [-1, 1], true)) {
            return;
        }

        $ordered = $this->currentLayout()?->sections()->orderBy('sort_order')->get();

        if ($ordered === null) {
            return;
        }

        $index = $ordered->search(fn (HomepageSection $s): bool => $s->is($section));
        $target = $ordered->get($index + $direction);

        if ($target === null) {
            return;
        }

        // Rewrite the whole run rather than swapping two values: seeded rows can
        // share a sort_order, and a swap between equals moves nothing.
        $reordered = $ordered->all();
        [$reordered[$index], $reordered[$index + $direction]] = [$reordered[$index + $direction], $reordered[$index]];

        $position = 1;

        foreach ($reordered as $row) {
            $row->forceFill(['sort_order' => $position++])->save();
        }

        Cache::tags('homepage')->flush();
    }

    public function toggleSection(int $sectionId): void
    {
        $section = $this->ownedSection($sectionId);

        if ($section === null) {
            return;
        }

        $section->forceFill(['is_visible' => ! $section->is_visible])->save();

        Cache::tags('homepage')->flush();
    }

    public function deleteSection(int $sectionId): void
    {
        $section = $this->ownedSection($sectionId);

        if ($section === null) {
            return;
        }

        $section->delete();

        Cache::tags('homepage')->flush();

        Notification::make()->success()->title('حُذف القسم')->send();
    }

    private function ownedSection(int $sectionId): ?HomepageSection
    {
        $section = HomepageSection::query()->whereKey($sectionId)->first();

        return $section !== null && (int) $section->layout_id === (int) $this->layoutId
            ? $section
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addSectionAction(),
            $this->editSectionAction(),
            $this->activateAction(),
            $this->duplicateAction(),
            $this->scheduleAction(),
            $this->previewAction(),
        ];
    }

    private function addSectionAction(): Action
    {
        return Action::make('addSection')
            ->label('أضف قسمًا')
            ->icon('heroicon-o-plus')
            ->schema($this->sectionSchema())
            ->action(function (array $data): void {
                $layout = $this->currentLayout();

                if ($layout === null) {
                    return;
                }

                $layout->sections()->create([
                    'type' => $data['type'],
                    'title' => array_filter(['ar' => $data['title_ar'] ?? null, 'en' => $data['title_en'] ?? null]),
                    'source' => $data['source'],
                    'config' => $this->configFrom($data),
                    'sort_order' => ((int) $layout->sections()->max('sort_order')) + 1,
                    'is_visible' => true,
                ]);

                Cache::tags('homepage')->flush();

                Notification::make()->success()->title('أُضيف القسم')->send();
            });
    }

    private function editSectionAction(): Action
    {
        return Action::make('editSection')
            ->label('تحرير قسم')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->schema([
                Select::make('section_id')
                    ->label('القسم')
                    ->options(fn (): array => $this->sections()
                        ->mapWithKeys(fn (HomepageSection $s): array => [
                            $s->id => ($s->title() ?? HomepageSectionType::tryFrom($s->type)?->label() ?? $s->type),
                        ])->all())
                    ->required()
                    ->live(),
                ...$this->sectionSchema(),
            ])
            ->fillForm(fn (): array => [])
            ->action(function (array $data): void {
                $section = $this->ownedSection((int) $data['section_id']);

                if ($section === null) {
                    return;
                }

                $section->forceFill([
                    'type' => $data['type'],
                    'title' => array_filter(['ar' => $data['title_ar'] ?? null, 'en' => $data['title_en'] ?? null]),
                    'source' => $data['source'],
                    'config' => $this->configFrom($data),
                ])->save();

                Cache::tags('homepage')->flush();

                Notification::make()->success()->title('حُدّث القسم')->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function sectionSchema(): array
    {
        return [
            Select::make('type')
                ->label('نوع القسم')
                ->options(HomepageSectionType::options())
                ->required()
                ->live(),

            Select::make('source')
                ->label('مصدر المحتوى')
                ->options([
                    'auto' => 'تلقائي (قاعدة)',
                    'manual' => 'يدوي (اختيار المحرر)',
                    'mixed' => 'مختلط (مثبّت ثم تلقائي)',
                ])
                ->default('auto')
                ->required()
                ->live()
                ->helperText('المختلط يملأ الخانات المتبقية تلقائيًا دون تكرار أي مادة على الصفحة.'),

            TextInput::make('title_ar')->label('العنوان (عربي)'),
            TextInput::make('title_en')->label('العنوان (English)')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TextInput::make('limit')
                ->label('عدد العناصر')
                ->numeric()
                ->minValue(0)
                ->maxValue(20)
                ->default(fn (Get $get): int => HomepageSectionType::tryFrom((string) $get('type'))?->defaultLimit() ?? 4),

            /*
             * Only published articles are offered, with their gate status shown:
             * pinning something that cannot go out is a trap, not a choice.
             */
            Select::make('article_ids')
                ->label('المواد المثبّتة')
                ->multiple()
                ->searchable()
                ->options(fn (): array => Article::query()
                    ->published()
                    ->orderByDesc('published_at')
                    ->limit(200)
                    ->get()
                    ->mapWithKeys(fn (Article $a): array => [$a->id => $a->title])
                    ->all())
                ->visible(fn (Get $get): bool => in_array($get('source'), ['manual', 'mixed'], true))
                ->helperText('الترتيب هنا هو ترتيب العرض.'),

            Select::make('category_slug')
                ->label('القسم التحريري')
                ->options(fn (): array => Category::query()->with('translations')->get()
                    ->mapWithKeys(fn ($c): array => [$c->slug => $c->name ?? $c->slug])->all())
                ->visible(fn (Get $get): bool => in_array($get('source'), ['auto', 'mixed'], true)),

            Toggle::make('only_featured')
                ->label('المميزة فقط')
                ->visible(fn (Get $get): bool => in_array($get('source'), ['auto', 'mixed'], true)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function configFrom(array $data): array
    {
        return array_filter([
            'limit' => (int) ($data['limit'] ?? 4),
            'article_ids' => array_map('intval', (array) ($data['article_ids'] ?? [])),
            'category_slug' => $data['category_slug'] ?? null,
            'only_featured' => (bool) ($data['only_featured'] ?? false),
        ], fn ($value): bool => $value !== null && $value !== [] && $value !== false);
    }

    private function activateAction(): Action
    {
        return Action::make('activate')
            ->label('تفعيل')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->currentLayout()?->is_active === false)
            ->requiresConfirmation()
            ->modalDescription('سيصبح هذا التخطيط هو الصفحة الرئيسية، وتُعطّل بقية التخطيطات.')
            ->action(function (): void {
                $layout = $this->currentLayout();

                if ($layout === null) {
                    return;
                }

                app(ActivateLayout::class)($layout);

                Notification::make()->success()->title('فُعّل التخطيط')->send();
            });
    }

    private function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('نسخ')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->schema([TextInput::make('name')->label('اسم النسخة')->required()])
            ->action(function (array $data): void {
                $layout = $this->currentLayout();

                if ($layout === null) {
                    return;
                }

                $copy = app(DuplicateLayout::class)($layout, $data['name']);
                $this->layoutId = $copy->getKey();

                Notification::make()->success()->title('أُنشئت نسخة')->send();
            });
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('جدولة')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->schema([
                DateTimePicker::make('starts_at')->label('يبدأ')->seconds(false),
                DateTimePicker::make('ends_at')->label('ينتهي')->seconds(false),
            ])
            ->action(function (array $data): void {
                $layout = $this->currentLayout();

                if ($layout === null) {
                    return;
                }

                $layout->forceFill([
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                ])->save();

                Cache::tags('homepage')->flush();

                Notification::make()->success()->title('حُدّثت الجدولة')->send();
            });
    }

    /**
     * Opens the composed page in a new tab, including unpublished changes —
     * that is the point of a preview.
     */
    private function previewAction(): Action
    {
        return Action::make('preview')
            ->label('معاينة')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(fn (): string => route('admin.homepage.preview', ['layout' => $this->layoutId]))
            ->openUrlInNewTab();
    }
}
