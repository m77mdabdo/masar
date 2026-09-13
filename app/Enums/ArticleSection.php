<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four questions every significant MASAR story answers.
 *
 * CLAUDE.md §1 calls this a data model, not a slogan. It is: a body block
 * belongs to one of these, and the article page renders the four in order with
 * the blocks nested inside. A block with no section renders in a general
 * stream — opinion pieces and success stories do not take this shape, and
 * forcing them into it would produce four headings over one paragraph each.
 */
enum ArticleSection: string
{
    case WhatHappened = 'what_happened';
    case WhyItMatters = 'why_it_matters';
    case WhoIsAffected = 'who_is_affected';
    case Opportunity = 'opportunity';

    /** The small uppercase kicker above the section's headline. */
    public function label(): string
    {
        return match ($this) {
            self::WhatHappened => 'ما الذي حدث؟',
            self::WhyItMatters => 'لماذا يهم هذا؟',
            self::WhoIsAffected => 'من المتأثر؟',
            self::Opportunity => 'أين الفرصة؟',
        };
    }

    /**
     * The glyph in the icon tile. Inline SVG path data rather than an icon font:
     * four glyphs do not justify a font file on the critical path, and a font
     * that fails to load leaves a box where a meaning was.
     */
    public function iconPath(): string
    {
        return match ($this) {
            self::WhatHappened => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            self::WhyItMatters => 'M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7V17h8v-2.3A7 7 0 0012 2z',
            self::WhoIsAffected => 'M17 20h5v-2a3 3 0 00-5.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M12 14a5 5 0 014.6 3M7.4 17A5 5 0 0112 14m3-4a3 3 0 11-6 0 3 3 0 016 0zm6 1a2 2 0 11-4 0 2 2 0 014 0zM7 11a2 2 0 11-4 0 2 2 0 014 0z',
            self::Opportunity => 'M13 7l5 5m0 0l-5 5m5-5H6',
        };
    }

    /**
     * Which brand accent the icon tile and the section's rule carry. Only three
     * are used: the middle two questions share the neutral one so the page reads
     * as information → understanding → opportunity rather than four equal beats.
     */
    public function tone(): string
    {
        return match ($this) {
            self::WhatHappened => 'line',
            self::WhyItMatters => 'mint',
            self::WhoIsAffected => 'line',
            self::Opportunity => 'gold',
        };
    }

    /**
     * The article column that holds this question's answer.
     *
     * The columns are the editorial answer — `why_it_matters` is a publish-gate
     * requirement — and the blocks are the elaboration. A section leads with its
     * column and continues with its blocks, so a gate-required field can never
     * end up written but unrendered.
     */
    public function field(): string
    {
        return match ($this) {
            self::WhatHappened => 'what_happened',
            self::WhyItMatters => 'why_it_matters',
            self::WhoIsAffected => 'business_impact',
            self::Opportunity => 'opportunity',
        };
    }

    /**
     * @return array<string, string> value => label, for a Filament select.
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
