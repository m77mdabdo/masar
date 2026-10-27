<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The switch that keeps generated demo content out of Google under this brand.
 *
 * The 97 demo articles are AI-generated. They may run on a server; they may not
 * be indexed under the Masar masthead. If Google archives generated copy under
 * this name it attaches to the brand before a single real word is published,
 * and no later correction unpicks that.
 *
 * So one switch drives five things — the site-wide robots meta tag, an
 * `X-Robots-Tag` header on every response (feeds and images included),
 * robots.txt, the sitemap, and a banner the reader can see — and it
 * **defaults to closed**.
 * A fresh deploy with no settings row is not indexed. Someone has to decide to
 * open it, which is the correct direction for a decision that cannot be undone.
 */
final class DemoMode
{
    public const SETTING = 'site.demo_mode';

    public function isOn(): bool
    {
        $value = setting(self::SETTING);

        // Null means nobody has decided. Closed is the safe reading of that,
        // and it is what a fresh install gets.
        if ($value === null) {
            return (bool) config('masar.demo.enabled', true);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function isOff(): bool
    {
        return ! $this->isOn();
    }
}
