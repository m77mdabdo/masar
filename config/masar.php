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
        // Every card, tile and listing surface renders a hero image. An article
        // without one leaves a hole in the homepage grid, so the image itself is
        // a gate rule, not just its alt text.
        'require_hero_image' => true,
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
    | Admin panel
    |--------------------------------------------------------------------------
    | `session_lifetime` is in minutes and is deliberately shorter than the
    | application default: the panel holds embargoed stories and reader data.
    */

    'admin' => [
        'session_lifetime' => 60,
        'login_max_attempts' => 5,
        'login_decay_minutes' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    | `encrypted` lists the setting keys whose values are encrypted at rest and
    | redacted from the audit log. Adding a key here is the only thing needed to
    | protect a new secret — the repository and the model both read this list.
    |
    | `defaults` is what `setting()` returns before anyone has saved anything,
    | so a fresh install renders instead of erroring.
    */

    'settings' => [
        'cache_ttl' => 86400,

        'encrypted' => [
            'integrations.newsletter_key',
            'seo.verification_tags',
        ],

        'defaults' => [
            'identity.site_name' => 'مسار',
            'identity.tagline' => 'من المعلومة إلى الفرصة',
            // The strip above the masthead says what MASAR covers; the tagline
            // beside the wordmark says what it is for. They were the same
            // string, printed twice, eighty pixels apart.
            'identity.descriptor' => 'أعمال · اقتصاد · أسواق · فرص',
            'identity.logo_path' => null,
            'identity.favicon_path' => null,
            'identity.og_image_path' => null,

            'contact.editorial_email' => null,
            'contact.advertising_email' => null,
            'contact.careers_email' => null,
            'contact.general_email' => null,
            'contact.social' => [],

            'editorial.default_locale' => 'ar',
            'editorial.articles_per_page' => 12,
            'editorial.reading_speed_wpm' => 180,

            'seo.title_template' => ':title | مسار',
            'seo.robots' => "User-agent: *\nAllow: /\n",
            'seo.verification_tags' => null,

            'integrations.newsletter_key' => null,

            // Market figures. Every one is typed in by an editor and carries a
            // date and a source — there is no feed, and a figure without both
            // does not render. Empty by default on purpose: an installation
            // that has not been filled in shows a labelled empty state, never a
            // placeholder number a reader could mistake for a real one.
            'market.as_of' => null,
            'market.source' => null,
            'market.index' => [],
            'market.instruments' => [],
            'market.ticker' => [],
            'market.pulse' => [],
            'market.data' => [],

            'maintenance.enabled' => false,
            'maintenance.allowlist' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 404 logging
    |--------------------------------------------------------------------------
    | Paths matching any of these are never recorded. A media site's 404 log is
    | otherwise 90% vulnerability scanners, which buries the dead URLs that
    | actually cost us readers.
    */

    'not_found' => [
        'ignore_patterns' => [
            '#^/wp-#i',
            '#\.php$#i',
            '#^/\.env#i',
            '#^/\.git#i',
            '#^/vendor/#i',
            '#^/admin/#i',
            '#/(phpmyadmin|xmlrpc|cgi-bin)#i',
            '#\.(asp|aspx|jsp|cgi)$#i',
        ],
        'max_path_length' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Masar Intelligence
    |--------------------------------------------------------------------------
    | Source monitoring never publishes anything. It exists to start us earlier:
    | a decision reaches the public from ten outlets in minutes, and we arrive
    | later with what it means. This buys the head start.
    |
    | Every threshold lives here rather than in a class, for the same reason the
    | publish gate does — an operational number an editor or an ops engineer
    | may need to change should not require a deploy to read.
    */

    'intelligence' => [
        // Consecutive failures before a source disables itself and notifies.
        // A monitor that silently stops monitoring is worse than no monitor:
        // nobody notices the story we never saw.
        'failure_threshold' => 5,

        // Default poll interval for a new source, in minutes.
        'default_poll_minutes' => 30,

        // Ceiling on exponential backoff, in minutes. A source that has been
        // failing for a day should be retried hourly, not every 30 seconds.
        'max_backoff_minutes' => 360,

        // Identifies us honestly, and gives a publisher someone to contact
        // rather than a blocked IP range.
        'user_agent' => env(
            'MASAR_USER_AGENT',
            'MasarBot/1.0 (+https://masar.sa/bot; editorial monitoring; contact@masar.sa)',
        ),

        'timeout_seconds' => 15,

        // Items fetched per poll. A feed that returns 500 entries on first
        // contact should not become 500 inbox rows.
        'max_items_per_poll' => 50,

        'retention' => [
            // How long a publisher's own body text may be kept, where the
            // source's legal_mode permits keeping it at all. Purged by
            // masar:purge-intelligence.
            'raw_body_days' => 30,

            // How long a dismissed item stays recoverable before it is purged.
            'dismissed_days' => 30,
        ],

        'dedup' => [
            // Hamming distance between two 64-bit SimHashes below which titles
            // are treated as near-identical.
            //
            // Calibrated, not taken from the literature. The usual working
            // value of 3 assumes documents of hundreds of tokens; a headline is
            // seven or eight, so a single added word moves far more bits.
            // Measured on realistic Arabic headline pairs:
            //
            //   identical / diacritics / reordered .......  0
            //   one word added, "عاجل:" prefix, rewrite .. 11-14
            //   different story, same publisher .......... 25
            //   unrelated stories ........................ 26-33
            //
            // 16 sits in the gap. IntelligenceDedupTest pins the distribution,
            // so a change to the Arabic normaliser that shifts it fails loudly
            // rather than quietly flagging unrelated stories as duplicates.
            'simhash_max_distance' => 16,

            // How far back the title check looks. A story recurring months
            // later is a new story.
            'window_hours' => 72,
        ],

        'importance' => [
            // Weights for the four inputs. They sum to 1.0 so the score reads
            // as a percentage, and every input is stored alongside the score so
            // an editor can see why something ranked high and disagree with it.
            'weights' => [
                'source_trust' => 0.30,
                'document_type' => 0.30,
                'entity_match' => 0.25,
                'recency' => 0.15,
            ],

            // Age at which the recency input reaches zero.
            'recency_hours' => 48,
        ],

        // Keyword sets for the rules classifier, keyed by document type.
        //
        // Deliberately data and not code: the rules path is the cheap one, and
        // the way it gets cheaper still is an editor adding the phrase a
        // ministry actually uses — without a deploy. Matched against the
        // Arabic-folded title and summary, so diacritics and alef forms do not
        // matter.
        'classification' => [
            'decision' => ['قرار', 'يقرر', 'اعتماد', 'موافقة مجلس', 'صدر قرار', 'قرارا'],
            'regulation' => ['لائحة', 'اللوائح', 'نظام', 'تعديلات', 'ضوابط', 'اشتراطات', 'تنظيمية'],
            'tender' => ['مناقصة', 'طرح منافسة', 'منافسة عامة', 'كراسة الشروط', 'ترسية'],
            'licence' => ['ترخيص', 'تراخيص', 'رخصة', 'تصريح', 'جولة تراخيص'],
            'funding' => ['تمويل', 'استثمار', 'جولة تمويلية', 'صندوق', 'دعم مالي', 'ضمانات'],
            'appointment' => ['تعيين', 'تكليف', 'رئيسا تنفيذيا', 'يعين', 'استقالة'],
            'earnings' => ['نتائج مالية', 'أرباح', 'خسائر', 'الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع', 'قوائم مالية'],
            'statistic' => ['إحصاء', 'مؤشر', 'معدل', 'نسبة', 'بيانات', 'ارتفاع بنسبة', 'انخفاض بنسبة'],
            'report' => ['تقرير', 'دراسة', 'مسح', 'تحليل'],
            'announcement' => ['تعلن', 'أعلنت', 'إعلان', 'تدشين', 'إطلاق'],
        ],

        // Query params stripped during normalisation. Tracking parameters make
        // two identical URLs look different, which defeats stage one of dedup
        // before it starts.
        'strip_query_params' => [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'fbclid', 'mc_cid', 'mc_eid', 'ref', 'source',
        ],
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
            // Card thumbs render at 74-180px. Serving them from a ladder that
            // starts at 400 sends four times the pixels a 74px rail thumb can
            // use, on every card on the front page.
            'small' => 200,
            'thumb' => 400,
            'medium' => 900,
            'large' => 1800,
            'og' => 1200,
        ],
        'max_upload_kb' => 12288,
        'accepted' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],

        // Self-hosted video. H.264 in MP4 only: it is the one combination every
        // target browser decodes in hardware, and a format list is a promise
        // about playback, not about what a form will accept.
        'accepted_video' => ['video/mp4'],

        // What a single clip may weigh before it stops being worth self-hosting.
        'max_video_kb' => 10240,
    ],

];
