<?php

declare(strict_types=1);

namespace App\Filament\Pages;

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

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'الإعدادات';
    }

    public function getTitle(): string
    {
        return 'إعدادات الموقع';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(Settings::class);

        $this->form->fill(
            collect(array_keys((array) config('masar.settings.defaults')))
                ->mapWithKeys(fn (string $key): array => [
                    // Dots are the settings namespace, not a nesting hint.
                    str_replace('.', '__', $key) => $settings->get($key),
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
        return Tab::make('الهوية')->id('identity')->schema([
            TextInput::make('identity__site_name')->label('اسم الموقع')->required(),
            TextInput::make('identity__tagline')->label('الشعار النصي')
                ->helperText('يظهر بجوار الشعار في الترويسة. ثلاثة أسطر قصيرة.'),
            TextInput::make('identity__descriptor')->label('وصف التغطية')
                ->helperText('يظهر في الشريط العلوي.'),
            FileUpload::make('identity__logo_path')->label('الشعار')->image()->disk('public')->directory('site'),
            FileUpload::make('identity__favicon_path')->label('أيقونة الموقع')->image()->disk('public')->directory('site'),
            FileUpload::make('identity__og_image_path')->label('صورة المشاركة الافتراضية')->image()->disk('public')->directory('site')
                ->helperText('تُستخدم عندما لا تحدد المادة صورتها الخاصة.'),
        ])->columns(2);
    }

    private function contactTab(): Tab
    {
        return Tab::make('التواصل')->id('contact')->schema([
            TextInput::make('contact__editorial_email')->label('بريد التحرير')->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__advertising_email')->label('بريد الإعلانات')->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__careers_email')->label('بريد التوظيف')->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            TextInput::make('contact__general_email')->label('بريد عام')->email()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            KeyValue::make('contact__social')
                ->label('حسابات التواصل')
                ->keyLabel('المنصة')
                ->valueLabel('الرابط')
                ->columnSpanFull(),
        ])->columns(2);
    }

    private function editorialTab(): Tab
    {
        return Tab::make('التحرير')->id('editorial')->schema([
            Select::make('editorial__default_locale')
                ->label('اللغة الافتراضية')
                ->options(fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all())
                ->required(),
            TextInput::make('editorial__articles_per_page')->label('عدد المواد في الصفحة')
                ->numeric()->minValue(4)->maxValue(60)->required(),
            TextInput::make('editorial__reading_speed_wpm')->label('سرعة القراءة (كلمة/دقيقة)')
                ->numeric()->minValue(80)->maxValue(400)->required()
                ->helperText('العربية أبطأ من اللاتينية؛ الافتراضي 180.'),
        ])->columns(2);
    }

    private function seoTab(): Tab
    {
        return Tab::make('SEO')->id('seo')->schema([
            TextInput::make('seo__title_template')->label('قالب العنوان')
                ->helperText('استخدم :title موضعًا لعنوان الصفحة.')
                ->columnSpanFull(),
            Textarea::make('seo__robots')->label('robots.txt')->rows(6)
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),
            Textarea::make('seo__verification_tags')->label('وسوم التحقق')->rows(4)
                ->helperText('وسوم meta الخاصة بأدوات مشرفي المواقع. تُخزّن مشفّرة.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),
        ]);
    }

    private function integrationsTab(): Tab
    {
        return Tab::make('التكاملات')->id('integrations')->schema([
            // Wired to nothing on purpose, pending the provider choice. The
            // helper text says so out loud: a filled-in key that nothing reads
            // would let an editor believe sending is configured when the only
            // thing that works today is collecting addresses.
            TextInput::make('integrations__newsletter_key')->label('مفتاح مزوّد النشرة')
                ->password()->revealable()
                ->helperText('غير مستخدم حاليًا: لم يُختر مزوّد الإرسال بعد، ولا تُرسل النشرة من النظام. '
                    .'الاشتراك والتأكيد وإلغاء الاشتراك تعمل، أما الإرسال فينتظر هذا القرار. '
                    .'يُخزّن مشفّرًا ولا يظهر في سجل التدقيق.')
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
            TextInput::make('label')->label('الاسم')->required(),
            TextInput::make('value')->label('القيمة')->required()
                ->helperText('كما تُنشر: 11,234.56 أو ‎+4.6%‎ أو "31 مليار ريال".')
                ->extraInputAttributes(['dir' => 'auto']),
            TextInput::make('change')->label('التغير %')->numeric()
                ->helperText('موجب أو سالب. اتركه فارغًا إذا لم يُنشر تغير.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
        ];

        return Tab::make('الأسواق')->id('market')->schema([
            DateTimePicker::make('market__as_of')
                ->label('صالحة حتى')
                ->seconds(false)
                ->helperText('مطلوب. بدون تاريخ لا يُعرض أي رقم — القارئ سيفترض أنها لحظية.')
                ->columnSpan(1),

            TextInput::make('market__source')
                ->label('المصدر')
                ->helperText('الجهة التي نشرت هذه الأرقام. يظهر بجانب التاريخ.')
                ->columnSpan(1),

            Repeater::make('market__ticker')
                ->label('الشريط العلوي')
                ->schema([
                    ...$figure('ticker', 'مؤشر'),
                    Textarea::make('series')
                        ->label('نقاط المنحنى')
                        ->rows(2)
                        ->helperText('١٠ إلى ١٤ رقمًا مفصولة بفواصل، في سطر واحد. شكل الحركة فقط — بلا محور قيمة. بدونها لا يُرسم منحنى.')
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                        ->columnSpanFull(),
                ])
                ->columns(3)->maxItems(5)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Section::make('المؤشر الرئيسي')
                ->description('المؤشر الذي يرسمه المخطط.')
                ->schema([
                    TextInput::make('market__index.label')->label('الاسم'),
                    TextInput::make('market__index.value')->label('القيمة')
                        ->extraInputAttributes(['dir' => 'auto']),
                    TextInput::make('market__index.change')->label('التغير %')->numeric()
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                    Textarea::make('market__index.series')
                        ->label('نقاط المخطط')
                        ->rows(2)
                        ->helperText('أرقام مفصولة بفواصل. شكل الاتجاه فقط — يُرسم بلا محور قيمة.')
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Repeater::make('market__instruments')
                ->label('أدوات مالية')
                ->schema($figure('instruments', 'أداة'))
                ->columns(3)->maxItems(4)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Repeater::make('market__pulse')
                ->label('نبض المناطق')
                ->schema($figure('pulse', 'منطقة'))
                ->columns(3)->maxItems(6)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),

            Repeater::make('market__data')
                ->label('لوحة البيانات')
                ->schema($figure('data', 'مؤشر'))
                ->columns(3)->maxItems(4)->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columnSpanFull(),
        ])->columns(2);
    }

    private function maintenanceTab(): Tab
    {
        return Tab::make('الصيانة')->id('maintenance')->schema([
            Toggle::make('maintenance__enabled')
                ->label('وضع الصيانة')
                ->helperText('يوقف الموقع العام عن الزوار. لوحة التحكم تبقى متاحة.'),
            TagsInput::make('maintenance__allowlist')
                ->label('عناوين IP المسموح لها')
                ->placeholder('000.000.000.000')
                ->helperText('تتجاوز وضع الصيانة. أضف عنوانك قبل التفعيل.')
                ->columnSpanFull(),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

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

            $values[$key] = $value;
        }

        $settings->setMany($values);

        Notification::make()->success()->title('حُفظت الإعدادات')->send();
    }
}
