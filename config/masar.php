<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    | Six editions (owner's ruling, 2026-10-28). The key is the URL segment and
    | the value stored in every `locale` column, so it is lowercase and never
    | changes: `/ur-pk/…`, `/zh-hans/…`. `tag` is the BCP 47 form that goes in
    | `<html lang>`, hreflang and Open Graph — `ur-PK`, `zh-Hans`. The two are
    | different strings on purpose; mixing them up is the bug where hreflang
    | advertises a code no crawler recognises.
    |
    | `script` selects the type stack (`resources/css/app.css`, `[data-script]`).
    | Urdu is Nastaliq, not Arabic naskh: same block of Unicode, a different
    | writing tradition, and a reader in Pakistan reads naskh as foreign.
    |
    | `enabled` gates routing, the switcher, the sitemap and the root redirect.
    | All six are on (owner's ruling, 2026-10-29). An edition with no published
    | stories still serves its translated chrome, static pages and honest empty
    | states; it is never switched off for being empty.
    */

    'locales' => (function (): array {
        // All six are public by the owner's ruling (2026-10-29): the interface
        // is served while its translation review is pending, and that review
        // is recorded separately (translation_reviews). An environment can
        // still take an edition off; Arabic is always on.
        $enabled = array_filter(array_map('trim', explode(',', (string) env('MASAR_LOCALES_ENABLED', 'ar,en,fr,hi,ur-pk,zh-hans'))));
        $on = fn (string $code): bool => $code === 'ar' || in_array($code, $enabled, true);

        return [
            'ar' => ['name' => 'العربية', 'tag' => 'ar', 'og' => 'ar_SA', 'dir' => 'rtl', 'script' => 'arabic', 'enabled' => $on('ar')],
            'en' => ['name' => 'English', 'tag' => 'en', 'og' => 'en_US', 'dir' => 'ltr', 'script' => 'latin', 'enabled' => $on('en')],
            'fr' => ['name' => 'Français', 'tag' => 'fr', 'og' => 'fr_FR', 'dir' => 'ltr', 'script' => 'latin', 'enabled' => $on('fr')],
            'hi' => ['name' => 'हिन्दी', 'tag' => 'hi', 'og' => 'hi_IN', 'dir' => 'ltr', 'script' => 'devanagari', 'enabled' => $on('hi')],
            'ur-pk' => ['name' => 'اردو', 'tag' => 'ur-PK', 'og' => 'ur_PK', 'dir' => 'rtl', 'script' => 'nastaliq', 'enabled' => $on('ur-pk')],
            'zh-hans' => ['name' => '简体中文', 'tag' => 'zh-Hans', 'og' => 'zh_CN', 'dir' => 'ltr', 'script' => 'han', 'enabled' => $on('zh-hans')],
        ];
    })(),

    'default_locale' => 'ar',

    /*
    |--------------------------------------------------------------------------
    | Publish gate
    |--------------------------------------------------------------------------
    | An article cannot reach `published` without satisfying every enabled rule.
    | Enforced in Article::isPublishable() and, later, in the publish Action and
    | the Filament UI. Turning a rule off here turns it off everywhere.
    */

    /*
    |--------------------------------------------------------------------------
    | Translation
    |--------------------------------------------------------------------------
    | Machine translation produces drafts, never publications. A translated
    | article is its own row in the source's translation group and passes the
    | same workflow and gate as any other — plus `require_translation_review`,
    | which refuses one no person has signed off.
    |
    | `driver` is `null` by the owner's ruling (2026-10-28): no outside service.
    | The null provider declines, and the draft waits for a person to translate
    | it. `allow_external` is a second, independent switch: even with a real
    | driver configured, no article text leaves the server until it is true.
    */

    'translation' => [
        'driver' => env('MASAR_TRANSLATION_DRIVER', 'null'),
        'allow_external' => (bool) env('MASAR_TRANSLATION_ALLOW_EXTERNAL', false),

        // Open a draft in every enabled edition when an original is first
        // published. Not on creation: an unpublished draft is the newsroom's
        // and is not sent anywhere until it is public.
        'auto_on_publish' => (bool) env('MASAR_TRANSLATION_AUTO_ON_PUBLISH', false),

        // Re-run a translation when its source text changes. Never touches one
        // a person has reviewed: that one is flagged as outdated instead.
        'auto_retranslate' => (bool) env('MASAR_TRANSLATION_AUTO_RETRANSLATE', false),

        // Owner's ruling (2026-10-29): machine-assisted translations of the
        // demo stories may be published on the owner's authorisation, marked
        // owner_authorized — never "reviewed" — while native review is pending.
        // Off, the gate admits only translations a person has reviewed.
        'owner_authorized_publication' => (bool) env('MASAR_TRANSLATION_OWNER_AUTHORIZED', true),

        // Provider calls per minute, across all workers.
        'per_minute' => (int) env('MASAR_TRANSLATION_PER_MINUTE', 10),

        /*
         * Terms whose rendering is an editorial decision, not a dictionary
         * lookup. Sent to any provider with every request. Only institutions'
         * own published names in each language; where an institution
         * publishes no name in a language, the term is left out for that
         * language rather than invented.
         */
        'glossary' => [
            'رؤية السعودية 2030' => ['en' => 'Saudi Vision 2030'],
            'صندوق الاستثمارات العامة' => ['en' => 'Public Investment Fund'],
            'هيئة السوق المالية' => ['en' => 'Capital Market Authority'],
            'البنك المركزي السعودي' => ['en' => 'Saudi Central Bank'],
            'السوق المالية السعودية (تداول)' => ['en' => 'Saudi Exchange (Tadawul)'],
            'الهيئة العامة للإحصاء' => ['en' => 'General Authority for Statistics'],
            'وزارة الاستثمار' => ['en' => 'Ministry of Investment'],
            'هيئة الزكاة والضريبة والجمارك' => ['en' => 'Zakat, Tax and Customs Authority'],
            'صكوك' => ['en' => 'sukuk', 'fr' => 'sukuk'],
            'الطرح العام الأولي' => ['en' => 'initial public offering', 'fr' => 'introduction en bourse'],
        ],
    ],

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

        // A translated article publishes only once a person has reviewed it.
        // Machine output is a draft (CLAUDE.md §1: AI assists, a human approves).
        'require_translation_review' => true,

        'min_summary_points' => 2,
        'max_summary_points' => 5,

        // A scheduled article this many hours overdue is not published by the
        // scheduler. After an outage the first tick would otherwise publish a
        // backlog in one minute, each stamped with a date nobody chose.
        'stale_schedule_hours' => 24,
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

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    | Defaults to ON, and that default is the point: a fresh deploy is not
    | indexed until somebody decides otherwise. The setting `site.demo_mode`
    | overrides this once an owner has made that decision in the admin.
    */
    'demo' => [
        'enabled' => env('MASAR_DEMO_MODE', true),
    ],

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
            'site.demo_mode' => true,

            // The brand is MASAR in every edition (owner's ruling, 2026-10-29):
            // one value, not translated. The Arabic wordmark «مسار» is part of
            // the lockup's artwork in the masthead, not this setting.
            'identity.site_name' => 'MASAR',
            'identity.tagline' => ['ar' => 'من المعلومة إلى الفرصة', 'en' => 'From information to opportunity', 'fr' => 'De l’information à l’opportunité', 'hi' => 'सूचना से अवसर तक', 'ur-pk' => 'معلومات سے موقع تک', 'zh-hans' => '从信息到机遇'],
            // The strip above the masthead says what MASAR covers; the tagline
            // beside the wordmark says what it is for. They were the same
            // string, printed twice, eighty pixels apart.
            'identity.descriptor' => ['ar' => 'أعمال · اقتصاد · أسواق · فرص', 'en' => 'Business · Economy · Markets · Opportunities', 'fr' => 'Affaires · Économie · Marchés · Opportunités', 'hi' => 'कारोबार · अर्थव्यवस्था · बाज़ार · अवसर', 'ur-pk' => 'کاروبار · معیشت · مارکیٹس · مواقع', 'zh-hans' => '商业 · 经济 · 市场 · 机遇'],
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

            'seo.title_template' => ':title | MASAR',
            // Per edition. The description a page carries when it sets none.
            'seo.description' => ['ar' => 'منصة إعلامية عربية للأعمال والاقتصاد والأسواق والفرص، بتركيز على السوق السعودية.', 'en' => 'An Arabic-first media platform for business, the economy, markets and opportunities, focused on the Saudi market.', 'fr' => 'Une plateforme médiatique arabe consacrée aux affaires, à l’économie, aux marchés et aux opportunités, centrée sur le marché saoudien.', 'hi' => 'व्यापार, अर्थव्यवस्था, बाज़ार और अवसरों पर अरबी-प्रथम मीडिया मंच, सऊदी बाज़ार पर केंद्रित।', 'ur-pk' => 'کاروبار، معیشت، مارکیٹس اور مواقع پر عربی اوّلین میڈیا پلیٹ فارم، سعودی مارکیٹ پر مرکوز۔', 'zh-hans' => '以阿拉伯语为先的商业、经济、市场与机遇媒体平台，专注沙特市场。'],
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

    /*
    |--------------------------------------------------------------------------
    | Readership
    |--------------------------------------------------------------------------
    | Counted once per visitor per article, buffered in Redis, flushed on a
    | schedule. Ranking runs on a rolling window because "most read" on a news
    | site means this week — a lifetime counter ossifies and the section stops
    | changing.
    */
    'views' => [
        // How long a visitor's read of one article is remembered, so a refresh
        // is not a second read. A day: the same person returning tomorrow is a
        // genuine second read and worth counting.
        'dedupe_hours' => 24,

        // The ranking window.
        'window_days' => 7,

        /*
         * User agents never counted.
         *
         * Note this is a *user-agent* list and is deliberately separate from
         * `not_found.ignore_patterns`, which matches paths. A crawler fetching
         * a real article is not a 404 and would otherwise rank it.
         */
        'ignore_agents' => [
            '#bot#i', '#crawl#i', '#spider#i', '#slurp#i', '#fetch#i',
            '#headless#i', '#phantom#i', '#curl#i', '#wget#i', '#python-requests#i',
            '#lighthouse#i', '#pagespeed#i', '#gtmetrix#i', '#uptime#i', '#monitor#i',
            '#preview#i', '#facebookexternalhit#i', '#embedly#i',
        ],
    ],

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

        /*
         * What is worth waking someone for.
         *
         * An inbox an editor checks is not an alert; an alert is for the things
         * that do not wait. Two of them:
         *
         * - a source dying, because nothing arrives from it until someone acts
         * - an item scoring above this, because a regulator decision at 70+ is
         *   a story with a deadline
         *
         * Set `alert_importance` to null to stop the second entirely. A channel
         * that fires constantly is one people mute, and a muted alert is worse
         * than none because everyone believes it is working.
         */
        'alert_importance' => 75,

        // Default poll interval for a new source, in minutes.
        'default_poll_minutes' => 30,

        // Ceiling on exponential backoff, in minutes. A source that has been
        // failing for a day should be retried hourly, not every 30 seconds.
        'max_backoff_minutes' => 360,

        // Identifies us honestly, and gives a publisher someone to contact
        // rather than a blocked IP range.
        /*
         * Scheduled polling is OFF until the crawler's identity resolves.
         *
         * The User-Agent below names a URL and a mailbox. Both were sent to two
         * real publishers before either existed, which means a publisher who
         * wanted to ask us to slow down or stop had nowhere to write — the exact
         * situation the header exists to prevent. Politeness that cannot be
         * answered is performative.
         *
         * Turn this on when https://masar.sa/bot serves a page explaining what
         * the crawler is and how to block it, and contact@masar.sa reaches a
         * human. Until then `masar:check-sources` still runs by hand for
         * development; it just is not on a schedule.
         */
        'polling_enabled' => env('MASAR_POLLING_ENABLED', false),

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
            //
            // The earlier calibration was not fabricated and is reproducible:
            // on the constructed pairs it used, same-publisher-different-story
            // still measures 25 and unrelated still measures 30, and 16 does
            // sit between them. The flaw was the sample. Every one of those
            // pairs shared the stem "هيئة السوق المالية", so the shared tokens
            // held the distances high and the gap was an artefact of the
            // corpus, not a property of the method.
            //
            // Measured again against 50 real Arabic headlines from a live feed
            // and hand-labelled pairs that do not share a stem, **the gap
            // disappears and the classes overlap**:
            //
            //   same story, reworded ..................... 5, 8, 9, 14, 14, 20
            //   different stories ........................ 7, 14, 16, 23
            //   unrelated real pairs (1,225 of them) ..... min 17, p05 24, median 31
            //
            // "أرامكو تعلن نتائج الربع الثالث" against the identical sentence
            // with سابك in place of أرامكو — two companies, two stories —
            // measures 7, closer than most genuine rewrites. The reverse also
            // happens: البنك المركزي paraphrased as ساما is the same story at
            // 20. SimHash weights tokens by frequency, and across six or seven
            // tokens the shared domain words dominate while the one word that
            // identifies the subject barely moves a bit.
            //
            // So no threshold separates them. 10 is the least bad: it catches
            // 3 of 6 labelled duplicates and wrongly flags 1 of 4 different
            // stories, where 16 caught 5 of 6 but wrongly flagged 3 of 4 —
            // and across 70 real items 16 produced eight false positives and
            // no true positives.
            //
            // This is survivable only because the behaviour is **flag, never
            // discard**: a flagged item is still created and still in the
            // inbox, annotated. A wrong flag costs an editor a keystroke.
            //
            // **THE TITLE STAGE IS KNOWN-WEAK. Do not tune this number
            // expecting it to help.** Three labelled pairs are the evidence,
            // and DedupTest pins all three:
            //
            //   "أرامكو تعلن نتائج الربع الثالث"
            //   "سابك تعلن نتائج الربع الثالث" ................  7  DIFFERENT stories
            //
            //   "البنك المركزي يبقي على سعر الفائدة دون تغيير"
            //   "ساما تبقي سعر الفائدة دون تغيير" ............. 20  SAME story
            //
            //   "النحاس يتعافى من أدنى مستوى في 3 أسابيع"
            //   "حديث عن مرحلة جديدة في مسار القطاع" .......... 14  DIFFERENT stories
            //
            // A different story at 7 and a genuine rewrite at 20 is not a
            // threshold problem. The signal does not carry over six or seven
            // tokens: SimHash weights by frequency, shared domain words
            // dominate, and the one word naming the subject barely moves a bit.
            //
            // The replacement is an **entity-aware comparison**: two headlines
            // naming different companies cannot be duplicates however similar
            // the sentence, which is exactly what أرامكو vs سابك needs and what
            // no distance threshold can express. The entity extractor already
            // exists. Until then the URL stage carries the load — it produced
            // 18 true positives on the first feed that republished articles —
            // and this stage flags, never discards.
            //
            // DedupTest pins the measured overlap, including the pairs that
            // defeat it, so the next person does not re-derive a gap that is
            // not there. The previous distribution was also pinned by a test,
            // which is how a wrong number survived.
            'simhash_max_distance' => 10,

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

            // The three non-institutional forms. Keyed on movement verbs,
            // hedging verbs and attribution respectively, because that is what
            // actually distinguishes them in a headline — not the subject.
            //
            // `statistic` already owns "ارتفاع بنسبة" and "انخفاض بنسبة", which
            // is the right split: a percentage change reported as a figure is a
            // statistic, a price crossing a level is a market move.
            'market_move' => [
                'يتجاوز', 'تتجاوز', 'يتراجع', 'تتراجع', 'يرتفع', 'ترتفع',
                'يهبط', 'تهبط', 'يقفز', 'تقفز', 'يتعافى', 'تتعافى',
                'يستقر', 'تستقر', 'يفقد مكاسبه', 'إغلاق السوق',
                'برنت', 'خام برنت', 'وول ستريت', 'عوائد السندات',
            ],
            'forecast' => [
                'يتوقع', 'تتوقع', 'توقعات', 'المتوقع', 'يرجح', 'ترجح',
                'تقديرات', 'يستبعد', 'توقّع',
            ],
            'statement' => [
                'قال', 'قالت', 'صرح', 'صرحت', 'أكد', 'أكدت', 'يؤكد', 'تؤكد',
                'يشير', 'تشير', 'بحسب', 'نبحث', 'يرى', 'ترى',
            ],
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
        // JPEG, PNG and WebP only: every upload is re-encoded (App\Rules\SafeImage),
        // and AVIF is not a format GD re-encodes reliably.
        'accepted' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_dimension' => 8000,

        // Self-hosted video. H.264 in MP4 only: it is the one combination every
        // target browser decodes in hardware, and a format list is a promise
        // about playback, not about what a form will accept.
        'accepted_video' => ['video/mp4'],

        // What a single clip may weigh before it stops being worth self-hosting.
        'max_video_kb' => 10240,
    ],

];
