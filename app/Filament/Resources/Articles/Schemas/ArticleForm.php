<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Schemas;

use App\Actions\Support\GenerateSlug;
use App\Enums\ContentType;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder as BlockBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The article form.
 *
 * Seven tabs rather than one long scroll: a story in progress is worked on in
 * passes — write, then differentiate, then tag entities, then SEO — and a single
 * column forces an editor to scroll past six sections to reach the one they came
 * for.
 *
 * `status` appears nowhere. It is written only by TransitionArticleStatus, and a
 * form field would be a second, unaudited write path.
 */
class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make('article')
                    ->persistTabInQueryString()
                    ->columnSpan(2)
                    ->tabs([
                        self::storyTab(),
                        self::bodyTab(),
                        self::differentiationTab(),
                        self::entitiesTab(),
                        self::sourcesTab(),
                        self::seoTab(),
                        self::distributionTab(),
                    ]),

                /*
                 * The publish gate sits permanently beside the form, not behind
                 * the publish button. An editor should know what is missing
                 * while they work, not at the moment they try to ship.
                 *
                 * Edit only: a record that does not exist yet has nothing to
                 * evaluate.
                 */
                View::make('filament.article.publish-gate')
                    ->columnSpan(1)
                    ->visible(fn ($livewire): bool => $livewire instanceof EditArticle),
            ]);
    }

    private static function storyTab(): Tab
    {
        return Tab::make('القصة')
            ->id('story')
            ->icon(Heroicon::OutlinedNewspaper)
            ->schema([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('subtitle')
                    ->label('العنوان الفرعي')
                    ->maxLength(255)
                    ->columnSpanFull(),

                /*
                 * The slug is an editorial decision, so it is never written while
                 * the editor types. "اقترح" fills it on demand; after publication
                 * changing it silently would break every link already shared.
                 */
                TextInput::make('slug')
                    ->label('المسار (slug)')
                    ->required()
                    ->maxLength(255)
                    ->helperText('حروف لاتينية فقط. تغييره بعد النشر يكسر الروابط المنشورة.')
                    ->rules(['regex:/^[a-z0-9-]+$/'])
                    ->validationMessages(['regex' => 'يُسمح بالحروف اللاتينية الصغيرة والأرقام والشرطة فقط.'])
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                    ->suffixAction(
                        Action::make('suggestSlug')
                            ->label('اقترح')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(function (Get $get, Set $set, ?Article $record): void {
                                $title = (string) $get('title');

                                if (blank($title)) {
                                    return;
                                }

                                $set('slug', app(GenerateSlug::class)(
                                    $title,
                                    (string) ($get('locale') ?: config('masar.default_locale')),
                                    Article::class,
                                    $record?->getKey(),
                                ));
                            }),
                    )
                    ->columnSpanFull(),

                Select::make('category_id')
                    ->label('القسم')
                    ->relationship('category', 'slug')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('content_type')
                    ->label('نوع المحتوى')
                    ->options(ContentType::options())
                    ->default(ContentType::News->value)
                    ->required()
                    ->live(),

                Select::make('locale')
                    ->label('اللغة')
                    ->options(fn (): array => collect(config('masar.locales'))
                        ->map(fn (array $l): string => $l['name'])
                        ->all())
                    ->default(config('masar.default_locale'))
                    ->required(),

                Toggle::make('is_sponsored')
                    ->label('محتوى مدفوع')
                    ->live()
                    ->helperText('يجب الإفصاح عنه للقارئ.'),

                TextInput::make('sponsor_name')
                    ->label('اسم الجهة الراعية')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('is_sponsored'))
                    ->required(fn (Get $get): bool => (bool) $get('is_sponsored')),

                SpatieMediaLibraryFileUpload::make('hero')
                    ->label('صورة الغلاف')
                    ->collection('hero')
                    ->image()
                    ->imageEditor()
                    ->maxSize((int) config('masar.media.max_upload_kb'))
                    ->acceptedFileTypes(config('masar.media.accepted'))
                    ->helperText('مطلوبة للنشر — كل بطاقات العرض والقوائم تعتمد عليها.')
                    ->columnSpanFull(),

                TextInput::make('hero_alt')
                    ->label('النص البديل للغلاف')
                    ->maxLength(255)
                    ->helperText('صِف الصورة لقارئ لا يراها. مطلوب للنشر.')
                    ->columnSpanFull(),

                TextInput::make('hero_credit')
                    ->label('حقوق الصورة')
                    ->maxLength(255)
                    ->columnSpanFull(),

                /*
                 * The 30-second summary. Limits come from config so the gate and
                 * the form can never disagree about what "enough" means.
                 */
                Repeater::make('summary')
                    ->label('ملخص الثلاثين ثانية')
                    ->simple(
                        TextInput::make('point')
                            ->label('نقطة')
                            ->required()
                            ->maxLength(300),
                    )
                    ->minItems((int) config('masar.publish_gate.min_summary_points'))
                    ->maxItems((int) config('masar.publish_gate.max_summary_points'))
                    ->defaultItems(2)
                    ->reorderable()
                    ->helperText(fn (Get $get): string => sprintf(
                        'المطلوب من %d إلى %d نقاط. الحالي: %d.',
                        (int) config('masar.publish_gate.min_summary_points'),
                        (int) config('masar.publish_gate.max_summary_points'),
                        count((array) ($get('summary') ?? [])),
                    ))
                    ->live()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function bodyTab(): Tab
    {
        return Tab::make('المتن')
            ->id('body')
            ->icon(Heroicon::OutlinedBars3BottomRight)
            ->schema([
                BlockBuilder::make('blocks')
                    ->label('كتل المحتوى')
                    ->addActionLabel('أضف كتلة')
                    ->collapsible()
                    ->collapsed()
                    ->cloneable()
                    ->blockNumbers(false)
                    ->blocks(ArticleBlocks::all())
                    ->columnSpanFull(),
            ]);
    }

    private static function differentiationTab(): Tab
    {
        return Tab::make('التمايز')
            ->id('differentiation')
            ->icon(Heroicon::OutlinedLightBulb)
            ->badge(fn (Get $get): ?string => blank($get('why_it_matters')) ? '!' : null)
            ->badgeColor('danger')
            ->schema([
                Textarea::make('why_it_matters')
                    ->label('لماذا يهم هذا؟')
                    ->required()
                    ->rows(4)
                    ->helperText('ما الذي تغيّر فعليًا، ولمن. هذه هي المسافة بين مسار وبين وكالة أنباء — وهي مطلوبة للنشر.')
                    ->columnSpanFull(),

                Textarea::make('business_impact')
                    ->label('الأثر على الأعمال')
                    ->rows(4)
                    ->helperText('من المتأثر: أي قطاع، أي حجم شركة، وبأي اتجاه.')
                    ->columnSpanFull(),

                Textarea::make('opportunity')
                    ->label('أين الفرصة؟')
                    ->rows(4)
                    ->helperText('خطوة قابلة للتنفيذ لقارئ في موقع القرار. لا نصيحة استثمارية.')
                    ->columnSpanFull(),

                Repeater::make('key_numbers')
                    ->label('أرقام مفتاحية')
                    ->schema([
                        TextInput::make('value')
                            ->label('القيمة')
                            ->required()
                            ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                        TextInput::make('label')
                            ->label('الوصف')
                            ->required(),
                    ])
                    ->columns(2)
                    ->maxItems(6)
                    ->defaultItems(0)
                    ->addActionLabel('أضف رقمًا')
                    ->helperText('أرقام بصيغة غربية (0-9) دائمًا.')
                    ->columnSpanFull(),
            ]);
    }

    private static function entitiesTab(): Tab
    {
        return Tab::make('الكيانات')
            ->id('entities')
            ->icon(Heroicon::OutlinedShare)
            ->schema([
                Select::make('topic_ids')
                    ->label('المواضيع')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Topic::query()
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn ($t): array => [$t->id => $t->name ?? $t->slug])
                        ->all())
                    ->helperText('المواضيع تغذّي صفحات SEO وعدّاداتها.')
                    ->columnSpanFull(),

                ...ArticleEntityFields::selectors(),
            ]);
    }

    private static function sourcesTab(): Tab
    {
        return Tab::make('المصادر')
            ->id('sources')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->badge(fn (Get $get): ?string => ($c = count((array) ($get('sources') ?? []))) > 0 ? (string) $c : null)
            ->schema([
                Repeater::make('sources')
                    ->label('المصادر')
                    ->relationship('sources')
                    ->orderColumn('sort_order')
                    ->addActionLabel('أضف مصدرًا')
                    ->defaultItems(0)
                    ->live()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->collapsible()
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('url')
                            ->label('الرابط')
                            ->url()
                            ->helperText('مطلوب مصدر واحد على الأقل برابط قابل للتحقق.')
                            ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                            ->columnSpan(2),
                        TextInput::make('publisher')
                            ->label('الناشر'),
                        Select::make('source_type')
                            ->label('النوع')
                            ->options([
                                'official_document' => 'وثيقة رسمية',
                                'press_release' => 'بيان صحفي',
                                'interview' => 'مقابلة',
                                'report' => 'تقرير',
                                'data' => 'بيانات',
                            ])
                            ->default('official_document')
                            ->required(),
                        DatePicker::make('accessed_at')
                            ->label('تاريخ الاطلاع')
                            ->default(now()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->id('seo')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->schema([
                TextInput::make('meta_title')
                    ->label('عنوان الميتا')
                    ->maxLength(60)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => sprintf(
                        '%d / 60 حرفًا. يُفضّل بين 50 و60.',
                        mb_strlen((string) ($get('meta_title') ?: $get('title'))),
                    ))
                    ->columnSpanFull(),

                Textarea::make('meta_description')
                    ->label('وصف الميتا')
                    ->rows(3)
                    ->maxLength(160)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => sprintf(
                        '%d / 160 حرفًا.',
                        mb_strlen((string) $get('meta_description')),
                    ))
                    ->columnSpanFull(),

                View::make('filament.article.google-preview')
                    ->viewData([])
                    ->columnSpanFull(),

                TextInput::make('canonical_url')
                    ->label('الرابط الأساسي (canonical)')
                    ->url()
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                    ->columnSpanFull(),

                TextInput::make('og_image_path')
                    ->label('صورة المشاركة (OG)')
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                    ->columnSpanFull(),

                Toggle::make('noindex')
                    ->label('منع الأرشفة (noindex)')
                    ->helperText('استخدمه للصفحات المكررة أو المؤقتة فقط.'),
            ]);
    }

    private static function distributionTab(): Tab
    {
        return Tab::make('التوزيع')
            ->id('distribution')
            ->icon(Heroicon::OutlinedMegaphone)
            ->schema([
                Textarea::make('distribution.linkedin')
                    ->label('LinkedIn')
                    ->rows(3)
                    ->maxLength(3000)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => self::counter($get('distribution.linkedin'), 3000))
                    ->columnSpanFull(),

                Textarea::make('distribution.x')
                    ->label('X')
                    ->rows(2)
                    ->maxLength(280)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => self::counter($get('distribution.x'), 280))
                    ->columnSpanFull(),

                Textarea::make('distribution.instagram')
                    ->label('Instagram')
                    ->rows(3)
                    ->maxLength(2200)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => self::counter($get('distribution.instagram'), 2200))
                    ->columnSpanFull(),

                TextInput::make('distribution.newsletter')
                    ->label('عنوان النشرة البريدية')
                    ->maxLength(90)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => self::counter($get('distribution.newsletter'), 90))
                    ->columnSpanFull(),

                TextInput::make('distribution.push')
                    ->label('نص الإشعار')
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => self::counter($get('distribution.push'), 120))
                    ->columnSpanFull(),
            ]);
    }

    private static function counter(mixed $value, int $limit): string
    {
        return sprintf('%d / %d حرفًا.', mb_strlen((string) $value), $limit);
    }
}
