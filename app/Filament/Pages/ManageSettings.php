<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Settings;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Site settings.
 *
 * Every field here changes how the public site behaves, so saving writes an
 * audit entry through the Setting model and flushes the day-long cache
 * immediately — an owner who changes the site name and still sees the old one
 * will change it again and then report a bug.
 */
class ManageSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('النظام');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('الإعدادات');
    }

    public function getTitle(): string
    {
        return __('إعدادات الموقع');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Setting::class) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(Settings::class);

        $this->form->fill(
            collect(array_keys((array) config('masar.settings.defaults')))
                ->mapWithKeys(fn (string $key): array => [
                    // Dots are the settings namespace, not a nesting hint.
                    // A per-edition setting fills as its whole map.
                    str_replace('.', '__', $key) => in_array($key, Settings::PER_EDITION, true)
                        ? $settings->editions($key)
                        : $settings->get($key),
                ])
                ->all(),
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')->columnSpanFull()->tabs([
                    $this->identityTab(),
                    $this->contactTab(),
                    $this->editorialTab(),
                    $this->seoTab(),
                    $this->integrationsTab(),
                    $this->marketTab(),
                    $this->maintenanceTab(),
                ]),
            ]);
    }

    private function identityTab(): Tab
    {
        return Tab::make(__('الهوية'))->id('identity')->schema([
            TextInput::make('identity__site_name')->label(__('اسم الموقع'))->required(),
            // One input per edition: each language is its own value, and
            // saving writes each one into its own slot (Settings::setEditions).
            ...self::perEdition('identity__tagline', __('الشعار النصي'), __('يظهر بجوار الشعار في الترويسة. ثلاثة أسطر قصيرة.')),
            ...self::perEdition('identity__descriptor', __('وصف التغطية'), __('يظهر في الشريط العلوي.')),
            ...self::perEdition('seo__description', __('الوصف الافتراضي لمحركات البحث'), __('يُستخدم حين لا تحدد الصفحة وصفها.')),
            FileUpload::make('identity__logo_path')->label(__('الشعار'))->image()->disk('public')->directory('site')->maxSize((int) config('masar.media.max_upload_kb'))->acceptedFileTypes(config('masar.media.accepted'))->rules([new \App\Rules\SafeImage]),
            FileUpload::make('identity__favicon_path')->label(__('أيقونة الموقع'))->image()->disk('public')->directory('site')->maxSize((int) config('masar.media.max_upload_kb'))->acceptedFileTypes(config('masar.media.accepted'))->rules([new \App\Rules\SafeImage]),
            FileUpload::make('identity__og_image_path')->label(__('صورة المشاركة الافتراضية'))->image()->disk('public')->directory('site')->maxSize((int) config('masar.media.max_upload_kb'))->acceptedFileTypes(config('masar.media.accepted'))->rules([new \App\Rules\SafeImage])
                ->helperText(__('تُستخدم عندما لا تحدد المادة صورتها الخاصة.')),
        ])->columns(2);
    }

    private function contactTab(): Tab
    {
        return Tab::make(__('التواصل'))->id('contact')->schema([
            TextInput::make('contact__editorial_email')->label(__('بريد التحرير'))->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__advertising_email')->label(__('بريد الإعلانات'))->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__careers_email')->label(__('بريد التوظيف'))->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__general_email')->label(__('بريد عام'))->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            KeyValue::make('contact__social')
                ->label(__('حسابات التواصل'))
                ->keyLabel(__('المنصة'))
                ->valueLabel(__('الرابط'))
                ->columnSpanFull(),
        ])->columns(2);
    }

    private function editorialTab(): Tab
    {
        return Tab::make(__('التحرير'))->id('editorial')->schema([
            Select::make('editorial__default_locale')
                ->label(__('اللغة الافتراضية'))
                ->options(fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all())
                ->required(),
            TextInput::make('editorial__articles_per_page')->label(__('عدد المواد في الصفحة'))
                ->numeric()->minValue(4)->maxValue(60)->required(),
            TextInput::make('editorial__reading_speed_wpm')->label(__('سرعة القراءة (كلمة/دقيقة)'))
                ->numeric()->minValue(80)->maxValue(400)->required()
                ->helperText(__('العربية أبطأ من اللاتينية؛ الافتراضي 180.')),
        ])->columns(2);
    }

    private function seoTab(): Tab
    {
        return Tab::make('SEO')->id('seo')->schema([
            // The one switch. Placed first and above robots.txt on purpose:
            // while it is on, the field below it does nothing.
            Toggle::make('site__demo_mode')
                ->label(__('وضع النسخة التجريبية'))
                ->helperText(__('عند تفعيله: لا يُفهرس الموقع إطلاقًا (noindex, nofollow)، ويمنع robots.txt كل الزواحف، ولا تُنشر خرائط الموقع، ويظهر شريط «نسخة تجريبية» للقارئ. أوقفه فقط بعد نشر محتوى تحريري حقيقي — فهرسة محتوى تجريبي تحت اسم مسار لا يمكن التراجع عنها.'))
                ->columnSpanFull(),

            TextInput::make('seo__title_template')->label(__('قالب العنوان'))
                ->helperText(__('استخدم :title موضعًا لعنوان الصفحة.'))
                ->columnSpanFull(),
            Textarea::make('seo__robots')->label('robots.txt')->rows(6)
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),
            Textarea::make('seo__verification_tags')->label(__('وسوم التحقق'))->rows(4)
                ->helperText(__('وسوم meta الخاصة بأدوات مشرفي المواقع. تُخزّن مشفّرة.'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),
        ]);
    }

    private function integrationsTab(): Tab
    {
        return Tab::make(__('التكاملات'))->id('integrations')->schema([
            // Wired to nothing on purpose, pending the provider choice. The
            // helper text says so out loud: a filled-in key that nothing reads
            // would let an editor believe sending is configured when the only
            // thing that works today is collecting addresses.
            TextInput::make('integrations__newsletter_key')->label(__('مفتاح مزوّد النشرة'))
                ->password()->revealable()
                ->helperText(__('غير مستخدم حاليًا: لم يُختر مزوّد الإرسال بعد، ولا تُرسل النشرة من النظام. الاشتراك والتأكيد وإلغاء الاشتراك تعمل، أما الإرسال فينتظر هذا القرار. يُخزّن مشفّرًا ولا يظهر في سجل التدقيق.'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
        ])->columns(2);
    }

    /**
     * Every figure the market surfaces render.
     *
     * There is no feed and no licence: an editor types these in, and the date
     * and the source below are required before any of them render at all. A
     * number on a business page without both is a claim nobody stands behind.
     */
    private function marketTab(): Tab
    {
        $figure = fn (string $name, string $label): array => [
            TextInput::make('label')->label(__('الاسم'))->required(),
            TextInput::make('value')->label(__('القيمة'))->required()
                ->helperText(__('كما تُنشر: 11,234.56 أو ‎+4.6%‎ أو "31 مليار ريال".'))
                ->extraInputAttributes(['dir' => 'auto']),
            TextInput::make('change')->label(__('التغير %'))->numeric()
                ->helperText(__('موجب أو سالب. اتركه فارغًا إذا لم يُنشر تغير.'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
        ];

        return Tab::make(__('الأسواق'))->id('market')->schema([
            DateTimePicker::make('market__as_of')
                ->label(__('صالحة حتى'))
                ->seconds(false)
                ->helperText(__('مطلوب. بدون تاريخ لا يُعرض أي رقم — القارئ سيفترض أنها لحظية.'))
                ->columnSpan(1),

            TextInput::make('market__source')
                ->label(__('المصدر'))
                ->helperText(__('الجهة التي نشرت هذه الأرقام. يظهر بجانب التاريخ.'))
                ->columnSpan(1),

            Repeater::make('market__ticker')
                ->label(__('الشريط العلوي'))
                ->schema([
                    ...$figure('ticker', __('مؤشر')),
                    Textarea::make('series')
                        ->label(__('نقاط المنحنى'))
                        ->rows(2)
                        ->helperText(__('10 إلى 14 رقمًا مفصولة بفواصل، في سطر واحد. شكل الحركة فقط — بلا محور قيمة. بدونها لا يُرسم منحنى.'))
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                        ->columnSpanFull(),
                ])
                ->columns(3)->maxItems(5)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Section::make(__('المؤشر الرئيسي'))
                ->description(__('المؤشر الذي يرسمه المخطط.'))
                ->schema([
                    TextInput::make('market__index.label')->label(__('الاسم')),
                    TextInput::make('market__index.value')->label(__('القيمة'))
                        ->extraInputAttributes(['dir' => 'auto']),
                    TextInput::make('market__index.change')->label(__('التغير %'))->numeric()
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                    Textarea::make('market__index.series')
                        ->label(__('نقاط المخطط'))
                        ->rows(2)
                        ->helperText(__('أرقام مفصولة بفواصل. شكل الاتجاه فقط — يُرسم بلا محور قيمة.'))
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Repeater::make('market__instruments')
                ->label(__('أدوات مالية'))
                ->schema($figure('instruments', __('أداة')))
                ->columns(3)->maxItems(4)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Repeater::make('market__pulse')
                ->label(__('نبض المناطق'))
                ->schema($figure('pulse', __('منطقة')))
                ->columns(3)->maxItems(6)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Repeater::make('market__data')
                ->label(__('لوحة البيانات'))
                ->schema($figure('data', __('مؤشر')))
                ->columns(3)->maxItems(4)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),
        ])->columns(2);
    }

    private function maintenanceTab(): Tab
    {
        return Tab::make(__('الصيانة'))->id('maintenance')->schema([
            Toggle::make('maintenance__enabled')
                ->label(__('وضع الصيانة'))
                ->helperText(__('يوقف الموقع العام عن الزوار. لوحة التحكم تبقى متاحة.')),
            TagsInput::make('maintenance__allowlist')
                ->label(__('عناوين IP المسموح لها'))
                ->placeholder('000.000.000.000')
                ->helperText(__('تتجاوز وضع الصيانة. أضف عنوانك قبل التفعيل.'))
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, TextInput> */
    private static function perEdition(string $field, string $label, string $help): array
    {
        return array_map(
            fn (string $code): TextInput => TextInput::make("{$field}.{$code}")
                ->label($label.' — '.\App\Support\Locales::name($code))
                ->helperText($code === \App\Support\Locales::default() ? $help : null)
                ->extraInputAttributes(['lang' => \App\Support\Locales::tag($code), 'dir' => \App\Support\Locales::dir($code)]),
            \App\Support\Locales::codes(),
        );
    }

    public function save(): void
    {
        Gate::authorize('update', Setting::class);

        $state = $this->form->getState();
        $settings = app(Settings::class);

        $values = [];

        foreach ($state as $field => $value) {
            $key = str_replace('__', '.', $field);

            // A blank secret means "leave it alone", not "erase it" — the field
            // renders empty by design and saving the form must not wipe a key.
            if ($settings->isEncrypted($key) && blank($value)) {
                continue;
            }

            if (in_array($key, Settings::PER_EDITION, true)) {
                $settings->setEditions($key, (array) $value);

                continue;
            }

            $values[$key] = $value;
        }

        $settings->setMany($values);

        Notification::make()->success()->title(__('حُفظت الإعدادات'))->send();
    }
}
