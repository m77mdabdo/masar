<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleSection;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;

/**
 * The thirteen body block types.
 *
 * Every block defines `label()` from its own content, not just its type. An
 * editor scanning thirty collapsed blocks looking for one paragraph needs to see
 * the opening words, not thirty rows that all say "فقرة".
 */
class ArticleBlocks
{
    /**
     * @return array<int, Block>
     */
    public static function all(): array
    {
        return [
            self::paragraph(),
            self::heading(),
            self::image(),
            self::quote(),
            self::pullquote(),
            self::numbers(),
            self::table(),
            self::embed(),
            self::video(),
            self::callout(),
            self::opportunity(),
            self::explainer(),
            self::divider(),
        ];
    }

    /**
     * Which of the four questions this block answers.
     *
     * A property of the block rather than a group the editor drags blocks into:
     * reordering the body then cannot silently move a paragraph from "what
     * happened" to "why it matters". Leaving it empty is a real choice — an
     * opinion piece renders as one stream, and the four questions are not
     * imposed on writing that does not take that shape.
     */
    private static function sectionField(): Select
    {
        return Select::make('section')
            ->label('القسم')
            ->options(ArticleSection::options())
            ->placeholder('بلا قسم — يظهر في المتن العام')
            ->native(false)
            ->columnSpanFull();
    }

    /**
     * A short, content-derived preview for the collapsed state.
     */
    private static function preview(?string $text, string $fallback, int $length = 55): string
    {
        $text = trim(strip_tags((string) $text));

        return $text === '' ? $fallback : Str::limit($text, $length);
    }

    private static function paragraph(): Block
    {
        return Block::make('paragraph')
            ->label('فقرة')
            ->icon('heroicon-o-bars-3-bottom-right')
            ->schema([
                self::sectionField(),
                Textarea::make('text')->label('النص')->rows(5)->required(),
            ])
            ->label(fn (?array $state): string => self::preview($state['text'] ?? null, 'فقرة'));
    }

    private static function heading(): Block
    {
        return Block::make('heading')
            ->label('عنوان فرعي')
            ->icon('heroicon-o-hashtag')
            ->schema([
                self::sectionField(),
                Select::make('level')->label('المستوى')->options([2 => 'H2', 3 => 'H3', 4 => 'H4'])->default(2)->required(),
                TextInput::make('text')->label('النص')->required(),
            ])
            ->columns(2)
            ->label(fn (?array $state): string => 'عنوان · '.self::preview($state['text'] ?? null, '—', 40));
    }

    private static function image(): Block
    {
        return Block::make('image')
            ->label('صورة')
            ->icon('heroicon-o-photo')
            ->schema([
                self::sectionField(),
                FileUpload::make('path')
                    ->label('الصورة')
                    ->image()
                    ->disk('public')
                    ->directory('articles/blocks')
                    ->maxSize((int) config('masar.media.max_upload_kb'))
                    ->acceptedFileTypes(config('masar.media.accepted'))
                    ->required(),
                TextInput::make('alt')->label('النص البديل')->required()
                    ->helperText('مطلوب لكل صورة، بلا استثناء.'),
                TextInput::make('caption')->label('التعليق'),
                TextInput::make('credit')->label('الحقوق'),
            ])
            ->columns(2)
            ->label(fn (?array $state): string => 'صورة · '.self::preview($state['caption'] ?? $state['alt'] ?? null, '—', 40));
    }

    private static function quote(): Block
    {
        return Block::make('quote')
            ->label('اقتباس')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->schema([
                self::sectionField(),
                Textarea::make('text')->label('نص الاقتباس')->rows(3)->required(),
                TextInput::make('attribution')->label('القائل')
                    ->helperText('شخص حقيقي وافق على النشر. لا تنسب اقتباسًا لم يُقل.'),
                TextInput::make('role')->label('الصفة'),
            ])
            ->label(fn (?array $state): string => 'اقتباس · '.self::preview($state['attribution'] ?? $state['text'] ?? null, '—', 40));
    }

    private static function pullquote(): Block
    {
        return Block::make('pullquote')
            ->label('اقتباس بارز')
            ->icon('heroicon-o-sparkles')
            ->schema([
                self::sectionField(),
                Textarea::make('text')->label('النص')->rows(2)->required(),
            ])
            ->label(fn (?array $state): string => 'بارز · '.self::preview($state['text'] ?? null, '—', 40));
    }

    private static function numbers(): Block
    {
        return Block::make('numbers')
            ->label('أرقام')
            ->icon('heroicon-o-calculator')
            ->schema([
                self::sectionField(),
                Repeater::make('items')
                    ->label('الأرقام')
                    ->schema([
                        TextInput::make('value')->label('القيمة')->required()
                            ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                        TextInput::make('label')->label('الوصف')->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(2),
            ])
            ->label(fn (?array $state): string => 'أرقام · '.count($state['items'] ?? []).' عنصر');
    }

    private static function table(): Block
    {
        return Block::make('table')
            ->label('جدول')
            ->icon('heroicon-o-table-cells')
            ->schema([
                self::sectionField(),
                TextInput::make('caption')->label('عنوان الجدول'),
                Textarea::make('csv')->label('البيانات (CSV)')->rows(6)->required()
                    ->helperText('صف لكل سطر، والأعمدة مفصولة بفاصلة.')
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            ])
            ->label(fn (?array $state): string => 'جدول · '.self::preview($state['caption'] ?? null, '—', 40));
    }

    private static function embed(): Block
    {
        return Block::make('embed')
            ->label('تضمين')
            ->icon('heroicon-o-code-bracket')
            ->schema([
                self::sectionField(),
                TextInput::make('url')->label('الرابط')->url()->required()
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                TextInput::make('caption')->label('التعليق'),
            ])
            ->label(fn (?array $state): string => 'تضمين · '.self::preview($state['url'] ?? null, '—', 40));
    }

    private static function video(): Block
    {
        return Block::make('video')
            ->label('فيديو')
            ->icon('heroicon-o-play-circle')
            ->schema([
                self::sectionField(),
                TextInput::make('url')->label('الرابط')->url()->required()
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                TextInput::make('title')->label('العنوان'),
                TextInput::make('duration')->label('المدة'),
            ])
            ->columns(2)
            ->label(fn (?array $state): string => 'فيديو · '.self::preview($state['title'] ?? $state['url'] ?? null, '—', 40));
    }

    private static function callout(): Block
    {
        return Block::make('callout')
            ->label('تنبيه')
            ->icon('heroicon-o-information-circle')
            ->schema([
                self::sectionField(),
                Select::make('tone')->label('النبرة')->options([
                    'insight' => 'رؤية',
                    'warning' => 'تحذير',
                    'context' => 'سياق',
                ])->default('insight')->required(),
                Textarea::make('text')->label('النص')->rows(3)->required(),
            ])
            ->label(fn (?array $state): string => 'تنبيه · '.self::preview($state['text'] ?? null, '—', 40));
    }

    private static function opportunity(): Block
    {
        return Block::make('opportunity')
            ->label('فرصة')
            ->icon('heroicon-o-flag')
            ->schema([
                self::sectionField(),
                Textarea::make('text')->label('النص')->rows(3)->required(),
                TextInput::make('cta_label')->label('نص الزر'),
                TextInput::make('cta_url')->label('رابط الزر')->url()
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
            ])
            ->label(fn (?array $state): string => 'فرصة · '.self::preview($state['text'] ?? null, '—', 40));
    }

    private static function explainer(): Block
    {
        return Block::make('explainer')
            ->label('شرح')
            ->icon('heroicon-o-academic-cap')
            ->schema([
                self::sectionField(),
                TextInput::make('term')->label('المصطلح')->required(),
                Textarea::make('text')->label('الشرح')->rows(3)->required(),
            ])
            ->label(fn (?array $state): string => 'شرح · '.self::preview($state['term'] ?? null, '—', 40));
    }

    private static function divider(): Block
    {
        return Block::make('divider')
            ->label('فاصل')
            ->icon('heroicon-o-minus')
            ->schema([
                self::sectionField(),
                Toggle::make('spacious')->label('مسافة إضافية')->default(false),
            ])
            ->label('فاصل');
    }
}
