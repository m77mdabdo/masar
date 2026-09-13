<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a source is read.
 *
 * There is no HTML scraping here and there is not meant to be. A feed that
 * exists is a publisher saying they want to be read by machines; a page that
 * has none is saying the opposite, and the answer to that is to ask them, not
 * to parse their markup.
 */
enum SourceType: string
{
    case Rss = 'rss';
    case Atom = 'atom';
    case Api = 'api';
    case Sitemap = 'sitemap';

    public function label(): string
    {
        return match ($this) {
            self::Rss => 'RSS',
            self::Atom => 'Atom',
            self::Api => 'واجهة برمجية',
            self::Sitemap => 'خريطة موقع',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $c, self $case): array => $c + [$case->value => $case->label()],
            [],
        );
    }
}
