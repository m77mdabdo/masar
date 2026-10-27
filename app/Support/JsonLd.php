<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Encodes a schema.org graph for a `<script type="application/ld+json">` block.
 *
 * The flags are the whole point of this class existing.
 *
 * `JSON_HEX_TAG` turns `<` and `>` into `\u003C` / `\u003E`. Without it, an
 * article whose title contains `</script>` **ends the script element early**,
 * and everything after it is parsed as HTML. That is stored XSS reachable by
 * anyone who can type a headline, executing for every reader of the article.
 * It was live: five components encoded with
 * `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` and a title of
 * `</script><img src=x onerror=alert(1)>` put two working breakouts on the
 * rendered page.
 *
 * `JSON_HEX_AMP` closes the same door through an HTML entity.
 *
 * `JSON_UNESCAPED_UNICODE` stays: Arabic must not become `ال...`,
 * which is legal JSON and unreadable to anyone debugging the markup.
 * `JSON_UNESCAPED_SLASHES` stays too — it only affects `/`, which is harmless
 * once `<` can no longer appear, and it keeps URLs legible.
 *
 * One function, called by every schema component, because five copies of an
 * escaping rule is five chances for the sixth component to get it wrong.
 */
final class JsonLd
{
    private const FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP;

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function encode(array $schema): string
    {
        return (string) json_encode($schema, self::FLAGS);
    }
}
