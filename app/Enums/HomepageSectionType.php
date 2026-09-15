<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The section types the front page is built from.
 *
 * A backed enum rather than a free string so the composer, the admin picker and
 * the public renderer cannot disagree about what exists.
 */
enum HomepageSectionType: string
{
    case BigStory = 'big_story';
    case Leads = 'leads';
    case Tiles = 'tiles';
    case Saudi = 'saudi';
    case Markets = 'markets';
    case Business = 'business';
    case Opportunities = 'opportunities';
    case Intelligence = 'intelligence';
    case Insights = 'insights';
    case Stories = 'stories';
    case MostRead = 'most_read';
    case EditorsPicks = 'editors_picks';
    case Video = 'video';
    case Podcast = 'podcast';
    case Reports = 'reports';
    case Data = 'data';
    case Issue = 'issue';
    case Companies = 'companies';
    case Newsletter = 'newsletter';

    public function label(): string
    {
        return match ($this) {
            self::BigStory => 'القصة الكبرى',
            self::Leads => 'الأبرز',
            self::Tiles => 'مختارات',
            self::Saudi => 'السعودية والأسواق',
            self::Markets => 'الأسواق',
            self::Business => 'الأعمال والشركات',
            self::Opportunities => 'فرص',
            self::Intelligence => 'رصد وتحليل',
            self::Insights => 'رؤى وفرص',
            self::Stories => 'قصص نجاح',
            self::MostRead => 'الأكثر قراءة',
            self::EditorsPicks => 'اختيارات المحرر',
            self::Video => 'مرئيات',
            self::Podcast => 'بودكاست',
            self::Reports => 'تقارير',
            self::Data => 'بيانات',
            self::Issue => 'ملف العدد',
            self::Companies => 'شركات',
            self::Newsletter => 'النشرة البريدية',
        };
    }

    /**
     * Sections that resolve no content of their own — nothing to fetch, and
     * nothing for the page's global dedup to spend an article on.
     *
     * Tiles render the site's categories and Data renders editor-entered market
     * figures. Leaving them as article-backed cost eleven articles a page that
     * were resolved, deduplicated against, and then never rendered — which is
     * eleven articles of supply the later rails needed.
     */
    public function isStatic(): bool
    {
        return in_array($this, [self::Newsletter, self::Tiles, self::Data], true);
    }

    /**
     * Sections backed by opportunities rather than articles.
     */
    public function isOpportunities(): bool
    {
        return $this === self::Opportunities;
    }

    /**
     * Sections that list entities rather than content — a companies rail shows
     * companies, not the articles that mention them.
     */
    public function isCompanies(): bool
    {
        return $this === self::Companies;
    }

    public function defaultLimit(): int
    {
        // These are the counts the front page is designed around: three big
        // stories in the carousel, three secondary leads, six tiles, a feature
        // plus five rows.
        // A section that resolves more than its template renders is content an
        // editor placed and no reader ever sees.
        return match ($this) {
            self::BigStory => 3,
            self::Leads, self::Business => 3,
            self::Insights, self::Intelligence => 4,
            self::Markets, self::Stories, self::EditorsPicks,
            self::Podcast, self::Reports, self::Data => 5,
            self::Saudi, self::Opportunities, self::Video, self::Issue => 6,
            self::Tiles => 6,
            self::Companies => 8,
            self::MostRead => 10,
            self::Newsletter => 0,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
