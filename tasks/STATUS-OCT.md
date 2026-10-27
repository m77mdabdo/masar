# MASAR — state audit, October

Audit date: **2026-10-27, 19:39–20:10 Asia/Riyadh**. Read-only except for the one
CLAUDE.md row requested in §9. Every line names the command or file that
established it. Nothing was fixed.

This replaces the `STATUS-OCT.md` committed earlier today in `7d4fa61`. That
version was written while MySQL 3307 was down and the tree was uncommitted; both
have since changed, so it no longer describes the machine. It is in git history.

**An earlier version of this file was committed today in `7d4fa61` while MySQL was
unavailable; its numbers are void.**

## 0. Corrections to the brief

| brief said | measured | how |
|---|---|---|
| "nothing has been committed since 14 September" | commits on **15 Sept** (`ea92932`), **16 Sept** (`3453000`, 115 files) and **today 19:25** (`7d4fa61` "now commit", 77 files incl. a previous `STATUS-OCT.md`) | `git log -5 --format='%H %ci %s'`, `git show --stat` |
| CLAUDE.md calls the category page "the page nobody has examined" | that phrase is **not in CLAUDE.md** at HEAD. It is in `tasks/STATUS.md:423` (a dated 14 Sept snapshot). CLAUDE.md's stale claim was a different sentence: "No clean LCP has been taken since the fix, so the category page has no trusted number" | `grep -rn "nobody has examined"` |
| item 9 still to do | an **uncommitted CLAUDE.md diff already existed** at the start of this audit with a before/after for the category page, from an earlier session today. I re-measured independently and it reproduced; see §9 | `git diff CLAUDE.md` |
| tables `entities`, `navigation_items`, `newsletter_subscribers` | none exist. Entities are per-type tables; navigation is `menu_items`; subscribers is `subscribers`. Counted under the real names | `information_schema.tables` |

---

## 1. Git state

| | |
|---|---|
| branch | `main`, tracking `origin/main` — **in sync** (`git rev-parse origin/main` = HEAD, `git status -sb` → `## main...origin/main`) |
| last commit | `7d4fa614e3f30b2e4f4cc417728a67f0ea367130` · 2026-10-27 19:25:17 +0300 · "now commit" · Mohamed Abdo |
| tree | **not clean** — at audit start: ` M CLAUDE.md` only. At audit end: ` M CLAUDE.md`, ` M tasks/STATUS-OCT.md` (this file). No untracked paths (`git status --porcelain -uall`) |
| tags | 0 (`git tag \| wc -l`) |

The divergent remote history the earlier report warned about is resolved: HEAD and
`origin/main` are the same commit. The last five commit subjects are "now commit"
and four × "creat new projedt", which do not meet §6 (imperative, scoped).

## 2. Test suite

`php artisan test > /tmp/suite.txt 2>&1` (exit 0):

**866 passed · 0 failed · 0 skipped · 0 risky/incomplete · 2,956 assertions · 61.11 s.**

- No `FAIL`/`SKIP`/`⨯` lines in `/tmp/suite.txt`. The only grep hit for "incomplete"
  is a test name ("drops only the incomplete figure…").
- Skips in source that *could* fire: `tests/Feature/RepositoryWeightTest.php:34,69`
  `markTestSkipped('not a git checkout')`. Did not fire here. **On any CI that
  checks out without `.git` (tarball deploy, shallow export) the repository
  weight guard silently protects nothing.**
- No `->skip(`, `->todo(`, `->only()`, `markTestIncomplete` anywhere in `tests/`.

## 3. Database truth (`masar` on 127.0.0.1:3307, MySQL 8.0.46)

| table | rows |
|---|---|
| articles | **97** — published 72 · ready 8 · writing 6 · needs_revision 4 · scheduled 4 · seo 2 · assigned 1 · trashed 0 |
| article_blocks | 1,357 |
| media | 206 |
| sources | 10 |
| source_items | 114 |
| intelligence_items | 114 |
| entities, by type | companies 12 (company 8 · startup 4) · people 8 · countries 8 · industries 12 · markets 6 · topics 20 |
| entity_mentions | 264 — company→article 174 · person→article 90. **No other entity type and no content type other than `article` is ever mentioned** |
| homepage_layouts | 1 (with 18 homepage_sections) |
| menu_items (navigation) | 23, across 4 menus |
| users | 5 — editor_in_chief 1 · editor 1 · writer 1 · fact_checker 1 · **no role 1**. **0 super_admin. 0 with 2FA confirmed** |
| subscribers | 60 — confirmed 38 · pending 14 · unsubscribed 8 |
| activity_log | 1,616 |

### Publish gate on the 72 published rows

One query over `articles a LEFT JOIN media m ON m.id = a.hero_media_id WHERE status='published'`:

| gate rule | failing |
|---|---|
| no hero image (`hero_media_id` NULL) | **0** |
| hero id pointing at a missing media row | **0** |
| no hero alt text | **0** |
| no source with a non-empty URL (`article_sources`) | **0** |
| empty `why_it_matters` | **0** |
| summary not a 2–5 item JSON array | **0** (all 72 have exactly 3) |
| also checked — no `fact_checked_at` | 0 |
| also checked — sponsored without `sponsor_name` | 0 |
| `gate_failures_count` NULL / non-zero | 0 / 0 |

All 72 heroes are `collection_name = hero`, `is_illustrative = 0`.

**How they got there:** every one of the 72 has an audited `منشورة ← جاهزة للنشر`
transition in `activity_log` and a `نُشرت المادة` entry; the seeder publishes via
`app(PublishArticle::class)` (`DemoContentSeeder.php:241,377`). The only code
path that writes `published` without the gate is `ArticleFactory.php:124`, which
is test-only. No bypass found.

### Found while counting — not asked, but you need it

1. **The demo seed puts placeholder and Latin lorem slugs in public URLs.**
   `companies.slug` = `company-1`…`company-12`, `topics.slug` = `topic-1`…,
   `opportunities.slug` = `opportunity-1`…, people `minima-nobis-odio`,
   `dolorem-velit-repellat`…, articles `maxime-doloremque-eum-rerum`,
   `similique-quia-quo-repellat`…. All created by the 14 Sept seed run
   (`created_at 2026-09-14 20:54`). The featured issue is `/ar/issues/topic-2`.
   §7 forbids lorem in factories; §2 calls a slug an editorial decision. Demo mode
   keeps them out of the index, but every reader-visible URL in a demo reads as
   generated.
2. **A test or tinker run wrote factory rows into the dev database on
   2026-09-16 19:28.** Category #7 `aliquam-explicabo` («قسم aliquam-explicabo»,
   0 articles) at 19:28:15, user #5 `ohasan@example.org` (no role) at 19:28:16, and
   an `updated_at` touch on all 97 articles in the same window. None of it is in
   `activity_log`. Category #7 is live: `GET /ar/aliquam-explicabo` → **200**. It
   is in no menu. Cause unknown; `phpunit.xml:32–37` already documents a previous
   incident where an inherited `DB_DATABASE=masar` beat the forced test value.
3. **Four scheduled articles are overdue, and nothing will publish them until a
   scheduler runs:** #73 (due 2026-09-16), #74 (09-26), #71 and #72 (09-29), all
   `gate_failures_count = 0`. Horizon is running (pids 73474/73494/86506, since
   18:26); **no `schedule:work` / cron process exists** (`ps aux`). The first tick
   of `masar:publish-scheduled` (every minute per `schedule:list`) publishes all
   four in the same minute.
4. **Five opportunities still `open` past their deadline:** ids 11 (10-03), 7
   (10-11), 13 (10-17), 2 (10-19), 3 (10-23).
5. **The ticker is dated 2026-09-14 18:07** (`settings.market.as_of`) and still
   renders. It meets §5's four conditions; nothing caps its age.

## 4. Route surface

`php artisan route:list --json`: **119 routes** — web 31 · filament 57 · horizon 22 ·
livewire 2 · storage 2 · admin 1 · default 1 · unnamed 3 (`/up`, two livewire assets).

Public routes returning a view, each fetched live against `127.0.0.1:8000` and
mapped to the tests that GET them (`grep` for the route name and the literal path
in `tests/`, then the hits read to confirm they are requests, not strings):

| route | live | tests that request it |
|---|---|---|
| `web.home` `/ar` | 200 | HomepageTest, MastheadChromeTest, ContainmentTest, DemoModeTest, LocaleTest, RtlTest |
| `web.article.show` | 200 | ArticlePageTest, ArticleSectionsTest, IllustrativeMediaTest, PerformanceBudgetTest, ReadershipTest, RobotsPolicyTest |
| `web.category.show` | 200 (saudi, business, insights) | CategoryShapeTest, AboveTheFoldImageTest, PerformanceBudgetTest, RemainingPagesTest, SponsoredDisclosureTest |
| `web.author.show` | 200 | EntityPagesTest, RemainingPagesTest |
| `web.company.show` | 200 | CompanyProfileTest, RemainingPagesTest, RouteResolutionTest |
| `web.person.show` | 200 | EntityPagesTest, RemainingPagesTest, RouteResolutionTest |
| `web.topic.show` | 200 | NavigationLinkTest, RemainingPagesTest, RouteResolutionTest |
| `web.issue.show` | 200 (`topic-2`) | RemainingPagesTest |
| `web.markets` | 200 | AboveTheFoldImageTest, RemainingPagesTest |
| `web.opportunities.index` | 200 | OpportunityFilterTest, AccessibilityTest, RtlTest |
| `web.opportunity.show` | 200 | RemainingPagesTest, RouteResolutionTest |
| `web.video.index` | 200 | VideoFacadeTest, RemainingPagesTest |
| `web.search` | 200 | ArabicSearchTest, ContainmentTest, RemainingPagesTest, RtlTest |
| `web.newsletter` | 200 | NewsletterSubscriptionTest, RemainingPagesTest |
| `web.newsletter.confirm` | (signed) | NewsletterSubscriptionTest via `confirmationUrl()` — valid, tampered, expired |
| `web.newsletter.unsubscribe` | (signed) | NewsletterSubscriptionTest via `unsubscribeUrl()` |
| `web.about`, `web.contact` | 200 | RemainingPagesTest (`assertSee`) |
| `web.editorial-standards` | 200 | RemainingPagesTest |
| `web.team` | 200 | NoDeadLinksTest (incl. content assertion) |
| `web.advertise` | 200 | NoDeadLinksTest, SponsoredDisclosureTest |
| `web.corrections` | 200 | NoDeadLinksTest — **`assertOk()` only** |

**Uncovered view routes: none.** Weakest: `/ar/corrections`, whose only test
asserts a 200 — the page makes claims about how corrections are handled and no
test reads them. Non-view routes (`/`, `robots.txt`, `rss.xml`, `{locale}/rss.xml`,
sitemaps, `/up`, `ads.click`, the two newsletter POSTs) were not part of the
question; sitemaps 404 by design under demo mode.

Error pages: `GET /ar/no-such-page` → 404 with `<html lang="en">` and
`<title>Not Found</title>` — Laravel's stock English page.
`resources/views/errors/` does not exist.

## 5. Policy coverage — every Filament resource

`Gate::getPolicyFor($resource::getModel())` for each of the 17 resources in the
admin panel (tinker), then `Resource::canAccess()` per role — the method
Filament's `CanAuthorizeResourceAccess` calls with `abort_unless(…, 403)` on
**every** resource page, list, create and edit
(`vendor/filament/filament/src/Resources/Pages/Concerns/CanAuthorizeResourceAccess.php:19`).

| resource | model | gate | fact_checker | writer | editor | editor_in_chief |
|---|---|---|---|---|---|---|
| Articles | Article | **ArticlePolicy** | Y | Y | Y | Y |
| Categories | Category | **TaxonomyPolicy** | n | n | Y | Y |
| Companies | Company | **TaxonomyPolicy** | n | n | Y | Y |
| Countries | Country | **TaxonomyPolicy** | n | n | Y | Y |
| Industries | Industry | **TaxonomyPolicy** | n | n | Y | Y |
| Markets | Market | **TaxonomyPolicy** | n | n | Y | Y |
| Opportunities | Opportunity | **TaxonomyPolicy** | n | n | Y | Y |
| People | Person | **TaxonomyPolicy** | n | n | Y | Y |
| Topics | Topic | **TaxonomyPolicy** | n | n | Y | Y |
| Subscribers | Subscriber | **SubscriberPolicy** | n | n | n | Y |
| Ads | AdCampaign | `canViewAny` → `settings.manage` | n | n | n | Y |
| Audit log | Activity | `canViewAny` → `audit.view` | n | n | Y | Y |
| Intelligence | IntelligenceItem | `canViewAny` → `intelligence.triage` | n | n | Y | Y |
| Redirects | Redirect | `canViewAny` → `settings.manage` | n | n | n | Y |
| Roles | Role | `canViewAny` → `roles.manage` | n | n | n | n |
| Sources | Source | `canViewAny` → `intelligence.configure` | n | n | n | Y |
| Users | User | `canViewAny` → `users.manage` | n | n | n | Y |

**Unprotected resources: 0.** 10 bound to a policy, 7 gated by an explicit
resource-level permission check. A fact-checker and a writer reach Articles and
nothing else. Roles is reachable only by `super_admin`, of which there are none.

Caveats, measured not assumed:

- My first harness called the *page's* `canAccess()` and got ALLOW everywhere,
  including Subscribers. In Filament v4 that method is not where resource
  authorisation lives. The table above uses the method Filament actually calls;
  it agrees with `PanelAccessTest`.
- The 7 resource-gated resources have **no per-record check**: whoever passes
  `canViewAny` may create/edit/delete any row (Activity and Intelligence override
  `canCreate`/`canEdit`; the other five do not).
- Pages: all 6 override `canAccess()`. `ManageSettings` re-checks it on save
  (`ManageSettings.php:297`). **`NavigationManager` checks it only in `mount()`;
  its Livewire actions `saveTree()`, `toggleItem()`, `deleteItem()`
  (`NavigationManager.php:162,312,325`) do not re-check**, so a user demoted while
  the page is open keeps write access to the menus until they reload. Low, not
  open to strangers.

## 6. Orphan check

Each class name grepped across `app/ routes/ resources/views/ bootstrap/ config/
database/` (excluding its own file) and `tests/` separately; then the mechanism
that wires it confirmed where a name reference is not how it is reached.

| kind | count | finding |
|---|---|---|
| Actions | 28 | every one has a production caller |
| Events | 3 | all dispatched from `TransitionArticleStatus`; `php artisan event:list` shows `ArticlePublished → RecordStatusAudit, FlushArticleCaches`, `ArticleStatusChanged → RecordStatusAudit`, `ArticleUnpublished → FlushArticleCaches` |
| Listeners | 2 | no name reference (auto-discovered); live per `event:list` |
| Jobs | 3 | all dispatched (`CheckSourceJob`, `FlushCacheTags`, `RecordNotFound`) |
| Queries | 7 | all called |
| Commands | 10 | scheduled (`schedule:list`): `publish-scheduled` (1 min), `flush-views` (5 min), `flush-ad-impressions` (5 min), `purge-intelligence` (03:30). `check-sources` is registered only when `polling_enabled` (off → absent, as §5 requires). Manual: `smoke`, `recount` (both in `deploy/`), `owner`, `reclassify`, `decay` |
| Policy methods | 29 | `ArticlePolicy::transition/publish/factCheck/seo` — 1 caller each. CRUD verbs on resource-bound policies are called implicitly |

**Orphans:**

1. **`MenuItemPolicy` (5 methods) and `SettingPolicy` (5 methods) — registered in
   `AppServiceProvider.php:138–139`, invoked by nothing.** Neither model backs a
   Filament resource, so the implicit-CRUD exemption does not apply; the pages
   that edit them gate on permission strings (`navigation.manage`,
   `settings.manage`). Not a hole — dead code that reads as protection, the
   pattern §5 names.
2. **The ad-serving mechanism is reachable only from tests.** `x-ads.slot`
   (`resources/views/components/ads/slot.blade.php`) is used by **0** templates and
   by `tests/Feature/SiteControl/AdsTest.php` only. `ad_placements`, `ad_campaigns`,
   `ad_creatives` all hold 0 rows. The admin resource has a List page only
   (`app/Filament/Resources/Ads/Pages/ListAdCampaigns.php`) — no create or edit.
   Meanwhile `masar:flush-ad-impressions` runs every 5 minutes flushing a buffer
   nothing can write to.
3. **`masar:decay` has no test at all** (0 references to `DecayCheck` or
   `masar:decay` in `tests/`). Also untested by name: `flush-views`,
   `flush-ad-impressions`, `check-sources`.

Untested by class name but exercised through HTTP or another caller (not orphans):
`CreateArticleRevision`, `SyncArticleHeroMedia`, `FlushNavigationCache`,
`ConfirmSubscription`, `SubscribeToNewsletter`, `UnsubscribeFromNewsletter`,
`CheckSourceJob`, `RelatedArticlesQuery`.

## 7. Against `tasks/PLAN-to-25-sept.md`

The plan's end date passed 32 days ago. Judged against §7's definition of done.

| slot | state | what is missing, exactly |
|---|---|---|
| Finish Intelligence | **done** | — (`CreateDraftFromIntelligenceItem`, `masar:reclassify`, `STALE-SNAPSHOT-INVENTORY.md` present and wired) |
| Day 1–2 Deployment | **partial — code only** | `deploy/` scripts and nginx config exist; demo-mode switch works (`robots.txt` → `Disallow: /`, `<meta name="robots" content="noindex, nofollow">`, sitemaps 404). **Never done:** a server, a deploy, a restore test on a server, Sentry or any error tracker (none in `composer.json`), uptime monitoring, any production Lighthouse |
| Day 3–4 Pages | **done** | every page route renders 200 (§4). Economy and podcast struck by decision |
| Day 5 Control layer | **partial** | read-time importance (`ImportanceScorer`), notifications (`HighImportanceItem`, `SourceDisabled`), composer reorder in the UI (`HomepageComposer::reorderSections`), irreversible disclosure (`DisclosureCannotBeWithdrawn`) — present. **Ads: placements and campaigns not done** — no create/edit UI, no slot in any template, 0 rows (§6.2) |
| Day 6 Hardening | **partial** | done: numbers, commitments and orphan sweeps; policies; honeypot (`SubscribeToNewsletterRequest`); 2FA middleware. **Not done: designed 404/500** (stock English page, §4); **Lighthouse from production** (no server); **400px on every page** — `tasks/REPORT.md:236,375` record it for the homepage only |
| Day 7 Demo content | **started (re-dating only)** | archive spread 2021:2 · 2022:5 · 2023:7 · 2024:10 · 2025:16 · 2026:32. **Not started:** the 10–12 full-model articles, demo marking (no column), a complete issue or report, the connected graph. And the seed's slugs are placeholders (§3) — the opposite of "shows a magazine" |
| Day 8–9 Domain | **not started** | `MAIL_MAILER=log`, no ESP package, 0 `List-Unsubscribe` occurrences in `app/`/`resources/`, `polling_enabled` false, no `/bot` route |
| Day 10 Handover | **partial** | `tasks/HANDOVER.md` 105 lines covers settings, load-bearing gotchas and roles. **The six how-tos are not written** (run, deploy, add an editor, publish, change homepage, add a source). 0 tags. No final STATUS.md |

Definition-of-done checks I can state from this audit: `php artisan test` passes
(§2); no secrets found in this pass (not a full secret scan); no new migrations
were evaluated for rollback.

## 8. Environmental decay

| item | state | how |
|---|---|---|
| MySQL | 8.0.46 on 3307, `mysql@8.0 8.0.46_6` linked 2026-10-27 18:25 against `protobuf 36.2`. `mysql@8.4 8.4.11_5` is also installed | `select version(), @@port`, `brew list --versions` |
| composer.lock vs vendor | **in sync** — "Nothing to install, update or remove"; `composer validate` valid; platform reqs pass. Runtime PHP 8.5.2, pin 8.4.1 | `composer install --dry-run`, `check-platform-reqs --lock` |
| node / npm | node v20.20.0, `node_modules` present (253) | `node -v` |
| **`public/build` freshness** | **stale.** A fresh `vite build` into the scratchpad yields `app-CJbDSed6.css`; the committed manifest serves `app-Da_nRjMA.css` (JS and admin theme hashes match). The served CSS lacks `.leading-5`, used at `components/article/key-numbers.blade.php:54`, so that caption renders at the inherited line height. File mtimes are useless here (git set the manifest's to the commit second, 19:25:17) | `npx vite build --outDir <scratch>`, selector diff |
| Tailwind sources | Tailwind v4 auto-detection scans every non-gitignored file, so **`CLAUDE.md`, `REPORT.md` and `tests/Feature/Accessibility/InvariantsTest.php` feed class names into the public bundle.** That is how the fresh build gains a `.focus\:outline-none:focus` rule — the utility §5 forbids — with no template using it, and `.shrink` from prose | `git grep -lw focus:outline-none` |
| Meilisearch | not installed: no scout/meilisearch package, no env, nothing on :7700. Consistent with §9 (LIKE search until ~2,000 articles) | `composer.json`, `curl :7700/health` |
| `storage/logs` | 544 KB, single `laravel.log`. 10 ERROR lines today, **all before 18:25** (MySQL `Connection refused`); none since the restore | `grep ".ERROR"` |
| failed_jobs | 0. Redis `queues:default` 0, delayed 0, `jobs` table 0 | SQL, `redis-cli` |
| Redis views buffer | `masar-database-views:buffer` holds 4 reads (articles 21, 9 from 2026-09-16; 1, 8 from today) never flushed — the flush is scheduled and no scheduler runs | `redis-cli hgetall` |
| `masar:decay` | exit 0, no FAIL. Queue ok (worker running), 8 flows ok, 5 forms addressed. Seed check skipped (no `--with-seed`) | `php artisan masar:decay` |

**`masar:decay` is weaker than its table looks.** Against §7's rule that a health
check observes the artefact: *publish an article — ok, 72 published* is a row
count; *newsletter stores a subscriber — ok, POST route present* stores nothing;
*redirect becomes a 301 — ok, 0 redirects configured* passes vacuously; *source
poll reaches the inbox* counts inbox rows. The navigation and ticker rows do read
the rendered page.

## 9. CLAUDE.md correction

Re-measured on 2026-10-27, `php artisan serve` on 127.0.0.1:8000 (pid 74591,
already running), warm cache, Lighthouse 12.8.2 default mobile slow-4G, through
a scratch gzip proxy on :8001 that forwards to :8000 and rewrites the absolute
`APP_URL` links so assets are compressed too. `uses-text-compression` = 1 on every
run. APP_URL unchanged.

| page | LCP (ms) | FCP | CLS | `lcp-lazy-loaded` | load delay |
|---|---|---|---|---|---|
| `/ar/saudi` × 3 | **3686 · 3686 · 3686** | 2271–2416 | 0.000 | 1 | 4–14 ms |
| `/ar/business` × 2 | 4059 · 4059 | 1814–2269 | 0.000 | 1 | 4–17 ms |
| `/ar` (control) × 2 | 3086 · 3089 | 2111–2114 | 0.006–0.009 | 1 | 5 ms |

This reproduces the uncommitted figures already in the row (saudi 3685/3686/3764,
business 3910–4062, home 3095). The edit folds these runs into the row's *After*
sentence and names the harness. Before: 3725 ms, category page, 2026-09-14 audit;
diagnosis 2026-09-15 (2,521 ms load delay vs 114 ms fetch). Both are stated in the
row. No other line of CLAUDE.md changed (`git diff --stat CLAUDE.md` → 1 line).

## 10. What this audit wrote

Disclosed so nobody chases it later:

- **Self-inflicted, do not chase:** `not_founds` rows `/ar/no-such-page`
  (2026-10-27 19:44) and `/ar/issues/vision-2030` (2026-10-27 19:48), and the hit
  increments on `/sitemap.xml` and `/news-sitemap.xml` at 19:48, were all created
  by this audit's own requests. Kept on purpose.
- Lighthouse and curl traffic warmed the dev Redis cache and created sessions.
- `CLAUDE.md` (one row) and this file.
- Scratch only: Vite output, Lighthouse JSON, the proxy script — all under the
  session scratchpad, none in the repo.
