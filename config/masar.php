<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    | Arabic launches. English is architected from day one but disabled until
    | content exists — `enabled` gates the public locale switcher and routing,
    | not the schema, which is bilingual already.
    */

    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'enabled' => true],
        'en' => ['name' => 'English', 'dir' => 'ltr', 'enabled' => false],
    ],

    'default_locale' => 'ar',

    /*
    |--------------------------------------------------------------------------
    | Publish gate
    |--------------------------------------------------------------------------
    | An article cannot reach `published` without satisfying every enabled rule.
    | Enforced in Article::isPublishable() and, later, in the publish Action and
    | the Filament UI. Turning a rule off here turns it off everywhere.
    */

    'publish_gate' => [
        'require_summary' => true,
        'require_source' => true,
        'require_hero_alt' => true,
        'require_fact_check' => true,

        // "Why does it matter?" is the product promise, not a nice-to-have.
        'require_why_it_matters' => true,

        // Paid placement must be disclosed to the reader, every time.
        'require_sponsor_name' => true,

        'min_summary_points' => 2,
        'max_summary_points' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reading speed
    |--------------------------------------------------------------------------
    | Arabic reads slower than English: denser morphology, and diacritic-free
    | text needs more disambiguation per word.
    */

    'reading_speed_wpm' => 180,

    /*
    |--------------------------------------------------------------------------
    | Cache lifetimes (seconds)
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'homepage' => 900,
        'article' => 3600,
        'lists' => 600,
        'entity' => 3600,
        'navigation' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    | Conversion values are target widths in pixels. `accepted` is checked
    | against the real detected MIME type, never the file extension.
    */

    'media' => [
        'conversions' => [
            'thumb' => 400,
            'medium' => 900,
            'large' => 1800,
            'og' => 1200,
        ],
        'max_upload_kb' => 12288,
        'accepted' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
    ],

];
