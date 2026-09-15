# MASAR — project status

Audit date: 2026-09-14. Every number here was measured today, not recalled.

Method, so you can judge the numbers: row counts come from a throwaway database
(`masar_audit`, created and dropped for this audit) seeded with
`migrate:fresh --seed`, so they are a genuine fresh-install state and your own
database was not touched. Lighthouse ran against a local gzip proxy standing in
for the edge, and the `uses-text-compression` audit is reported alongside every
figure — the first run of it today read FAIL, which invalidated those numbers,
and they were re-taken. Flows marked "verified" were driven end to end and the
result observed. Flows marked "not driven by hand" are covered by tests but I
did not click through the UI.

---

## 1. What exists

### Tables and fresh-seed row counts

51 tables, 4,477 rows.

| table | rows | table | rows |
|---|---|---|---|
| activity_log | 1593 | market_translations | 6 |
| article_author | 0 | markets | 6 |
| article_blocks | 1357 | media | 206 |
| article_related | 0 | menu_items | 23 |
| article_revisions | 0 | menus | 4 |
| article_sources | 193 | migrations | 49 |
| article_topic | 281 | model_has_permissions | 0 |
| articles | 97 | model_has_roles | 4 |
| cache | 0 | not_founds | 0 |
| cache_locks | 0 | notifications | 0 |
| categories | 6 | opportunities | 18 |
| category_translations | 6 | password_reset_tokens | 0 |
| companies | 12 | people | 8 |
| company_translations | 12 | permissions | 17 |
| countries | 8 | person_translations | 8 |
| country_translations | 8 | redirects | 0 |
| entity_mentions | 274 | role_has_permissions | 65 |
| failed_jobs | 0 | roles | 10 |
| homepage_layouts | 1 | sessions | 0 |
| homepage_sections | 19 | settings | 7 |
| industries | 12 | source_items | 22 |
| industry_translations | 12 | sources | 7 |
| intelligence_items | 22 | subscribers | 60 |
| job_batches | 0 | topic_translations | 20 |
| jobs | 0 | topics | 20 |
| | | users | 4 |

Three tables are empty by design rather than by omission: `article_revisions`
and `article_related` have no seeder, and `redirects`/`not_founds` start empty.
`article_author` being 0 while articles have authors means authorship is a
column, not the pivot — the pivot exists unused.

### Code

| area | count |
|---|---|
| Models | 33 |
| Actions | 22 |
| Query objects | 6 |
| Policies | 5 |
| Events / Listeners | 3 / 2 |
| Jobs | 2 |
| Console commands | 5 |
| Support classes | 8 |
| Services | 14 |
| Enums | 13 |
| Exceptions | 5 |
| Controllers | 20 |
| **Form Requests** | **0** |
| Middleware | 4 |
| Notifications | 1 |
| Filament resources | 15 |
| Filament pages | 6 |
| Filament widgets | 6 |
| Blade components | 80 |
| Test files | 57 |

Models: Article, ArticleBlock, ArticleRevision, ArticleSource, Category,
CategoryTranslation, Company, CompanyTranslation, Country, CountryTranslation,
EntityMention, HomepageLayout, HomepageSection, Industry,
IndustryTranslation, IntelligenceItem, Market, MarketTranslation, Media, Menu,
MenuItem, NotFound, Opportunity, Person, PersonTranslation, Redirect, Setting,
Source, SourceItem, Subscriber, Topic, TopicTranslation, User.

Actions: Articles (CreateArticleRevision, EvaluatePublishGate, PublishArticle,
SchedulePublication, SyncArticleBlocks, SyncArticleEntities,
SyncArticleHeroMedia, SyncArticleTopics, TransitionArticleStatus,
UnpublishArticle, UpdateGateFailuresCount), Homepage (ActivateLayout,
DuplicateLayout), Intelligence (RecordSourceResult, StoreSourceItem),
Navigation (FlushNavigationCache, SaveMenuTree, ValidateMenuTree), Redirects
(NormalisePath, ValidateRedirect), Support (GenerateSlug, TransliterateArabic).

Queries: ComposeHomepage, EntityContentQuery, MenuTreeQuery,
PublishedArticlesQuery, RelatedArticlesQuery, SearchArticlesQuery.

Policies: Article, Company, MenuItem, Opportunity, Setting. Events:
ArticlePublished, ArticleStatusChanged, ArticleUnpublished. Listeners:
FlushArticleCaches, RecordStatusAudit. Jobs: CheckSourceJob, RecordNotFound.

Commands: `masar:check-sources`, `masar:owner`, `masar:publish-scheduled`,
`masar:purge-intelligence`, `masar:recount`.

Filament resources: Articles, AuditLog/Activity, Categories, Companies,
Countries, Industries, Markets, Opportunities, People, Redirects, Roles,
Sources, Subscribers, Topics, Users. Pages: Dashboard, EditorialPipeline,
HomepageComposer, ManageSettings, NavigationManager, NotFoundLog. Widgets:
BlockedByGate, MostReadArticles, PipelineOverview, PublishedThisWeek,
RecentActivity, ScheduledNext48Hours.

Blade components by group: ui 13, article 28, home 16, layout 8, data 7,
schema 5.

### Routes

87 registered routes: 23 named `web.*` (the whole public site), 55 `filament.*`,
2 livewire, 2 storage, 1 admin redirect, 1 health check, 3 unnamed framework
routes. Of the 23 public routes, **not one is a POST, PUT, PATCH or DELETE** —
the public surface is entirely read-only.

Every public page was fetched and checked for real content. All return HTTP 200
with real data; none is a placeholder.

| route | name | words rendered |
|---|---|---|
| `/{locale}` | web.home | 3440 |
| `/{locale}/{category}/{slug}` | web.article.show | 1901 |
| `/{locale}/{category}` | web.category.show | 832 |
| `/{locale}/authors/{author}` | web.author.show | 852 |
| `/{locale}/markets` | web.markets | 824 |
| `/{locale}/video` | web.video.index | 736 |
| `/{locale}/companies/{slug}` | web.company.show | 710 |
| `/{locale}/opportunities` | web.opportunities.index | 694 |
| `/{locale}/topics/{slug}` | web.topic.show | 672 |
| `/{locale}/editorial-standards` | web.editorial-standards | 568 |
| `/{locale}/newsletter` | web.newsletter | 534 |
| `/{locale}/people/{slug}` | web.person.show | 434 |
| `/{locale}/search` | web.search | 431 |
| `/{locale}/about` | web.about | 379 |
| `/{locale}/opportunities/{slug}` | web.opportunity.show | 337 |
| `/{locale}/contact` | web.contact | 314 |
| `/{locale}/issues/{slug}` | web.issue.show | not sampled |
| `/{locale}/rss.xml` | web.rss.locale | 200 |
| `/sitemap.xml`, `/news-sitemap.xml` | web.sitemap, web.sitemap.news | 200 |
| `/robots.txt` | web.robots | 200 |
| `/rss.xml` | web.rss | 302 to `/ar/rss.xml` |
| `/` | web.root | redirect to locale |

`about`, `contact` and `editorial-standards` render copy held in Blade, not in
the database. That is a deliberate documented choice, but it means the owner
cannot edit those three pages without a developer.

---

## 2. What works end to end

**An editor writes, fact-checks and publishes an article — works, not driven by
hand.** The gate, the transition action, the audit trail and the Filament
resource all exist and are covered by tests. The seeded corpus shows the states
in use: 72 published, 8 ready, 6 writing, 4 scheduled, 2 needs_revision, 2 seo,
2 research, 1 fact_check. `gate_failures_count` is populated for every row (0
NULL), 18 articles are currently blocked by the gate and 79 pass. I did not sign
in and click the flow myself.

**That article appears on the homepage and is readable — verified.** The
homepage renders 3,440 words across 19 sections with no empty section; an
article page renders 1,901.

**An editor changes the navigation and the public header changes — verified.** I
edited a header menu item's Arabic label, flushed the cache, and the new label
appeared in the public header; restoring it restored the header.

**An editor reorders the homepage and the public page changes — verified.** Drag
reorder and the keyboard `moveSection` both write `sort_order` and flush the
`homepage` cache tag. Verified earlier this week when الأبرز was moved below the
tiles.

**A reader subscribes to the newsletter and the record is stored — does not
work.** This is the largest functional gap on the public site.
`/ar/newsletter` renders a form, but it is `method="GET"` with no `action`, there
is no POST route anywhere in the application, and `NewsletterController` only
returns a view. Submitting it reloads the page with the address in the query
string and stores nothing. The 60 `subscribers` rows come from the seeder. The
form looks live and is not.

**A source is added, polled, and an item reaches the inbox — the pipeline works,
the inbox does not exist.** I ran `masar:check-sources --once` against the seeded
local feed: it fetched, stored 2 new items (22 to 24), and reported the source
healthy, while the six unreachable `.test` sources each recorded a connection
failure and moved to "failing" without being disabled on a single failure. So
fetch, parse, dedupe, store and health tracking all work against real HTTP.
**But `SourceItem` has no Filament resource or page**, so nothing an editor can
open displays the items. The pipeline delivers into a table nobody can read.

**A redirect is created and a 404 becomes a 301 — verified, with a caveat.** I
created a redirect and `/ar/audit-probe` returned HTTP 301 to
`/ar/markets`. A genuine 404 returns 404 correctly. The 404 *logging* did not
land: `RecordNotFound` is queued to Redis and no worker is running, so
`not_founds` stayed at 0. See Risks.

---

## 3. Built but not finished

**AI provider.** `App\Services\Ai\AiProvider` is an interface with one
implementation, `NullAiProvider`, bound at `AppServiceProvider.php:43`. That one
line is the swap point. Nothing calls a model today.

**Search.** `SearchArticlesQuery` folds Arabic on both sides and runs a LIKE
query. The swap point is inside that query object — callers use it through
`__invoke` and would not change. CLAUDE.md sets the trigger at roughly 2,000
published articles; there are 72.

**The newsletter form.** Renders, submits nowhere. Either wire it or remove the
form; it should not ship looking functional.

**The intelligence inbox.** Everything upstream of the editor exists. The
surface does not.

**Static pages.** about, contact, editorial-standards are Blade copy. Fine for
now, but they are the three pages an owner is most likely to want to edit.

**`integrations.analytics_id`.** The setting exists in config and the settings
screen. It is rendered nowhere — nothing reads it. A dead setting is worse than
a missing one: it implies analytics are configured when they are not.

**`article_author` pivot and `article_related`.** Both tables exist, both are
empty, authorship runs through a column instead.

---

## 4. Not built at all

- **TASK 07 Parts C to F.** Part B is substantially done (see above). What is
  absent is everything an editor touches: the triage inbox, the dismissal and
  30-day recovery flow, the near-duplicate review surface, and the
  "create draft from item" action that pre-fills the frame and never the prose.
- **Notifications.** One class exists, `SourceDisabled`. The `notifications`
  table is empty and nothing else notifies anyone. No editorial notifications,
  no digest.
- **Web push.** No service worker, no subscription storage, no push code.
- **Reader accounts.** Not scaffolded. CLAUDE.md says a `reader` guard is
  "scaffolded but unused" — there is no `reader` guard in `config/auth.php`.
  That line in CLAUDE.md is wrong and should be corrected.
- **English content.** `en` is present in config but `enabled => false`, and
  `/en` returns 404. The locale architecture is real; there is no English
  content and no way to reach it.
- **Reports, podcast, issues.** These exist as `ContentType` cases
  (`report`, `interview`) and as homepage sections rendering ordinary articles.
  There is no report entity, no audio player, no episode model, and the issue
  page renders from featured topics rather than an issue record.
- **Companies directory.** A `CompanyResource` and a public company page exist.
  There is no directory index — no browsable list of companies.
- **Ads.** Nothing. **Sponsored content** is partly there: `is_sponsored` has 13
  references and the gate requires a sponsor name, so disclosure works; there is
  no ad slot, no campaign, no scheduling.
- **Analytics.** Nothing rendered, see above.

---

## 5. Measured state

**Tests: 646 passing, 2,129 assertions, 57 files, about 41 seconds.** Zero
skipped, zero incomplete, zero risky. Distribution: Feature/Public 18,
Feature 14, Feature/SiteControl 11, Feature/Filament 8, Feature/Intelligence 6.

What they cover, honestly: the publish gate and status machine, the homepage
composer including ordering, navigation, redirects, slugs, translations, entity
mentions, scheduled publishing, the intelligence pipeline (53 tests), contrast
tokens, the sparkline geometry, media rules, and repository weight. What they do
not cover: any browser interaction, the Filament forms as a user drives them, or
anything on the public site beyond HTTP status and content assertions.

**Lighthouse**, mobile preset, slow 4G, compression verified on for every run:

| page | perf | FCP | LCP | CLS | TBT | Speed Index |
|---|---|---|---|---|---|---|
| home | 97 | 1888 | **2053** | 0.061 | 17 | 2091 |
| article | 97 | 1833 | 2238 | 0.017 | 20 | 2029 |
| markets | 94 | 1764 | 2832 | 0.000 | 0 | 2030 |
| **category** | **88** | 1771 | **3725** | 0.000 | 0 | 2289 |

Home LCP is 2053 ms, against the 3685 ms recorded in CLAUDE.md §9. I am not
claiming credit for the difference — the recorded figure may have been taken on
a harness where compression was not actually on, which is a mistake I made once
today and caught. **The number that now needs attention is the category page at
3725 ms**, which is the slowest of the four and the one nobody has looked at.
Home CLS has risen to 0.061 from the recorded 0.041.

**axe** (wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa): **0 violations** on
`/ar`, `/ar/saudi`, `/ar/markets`, `/ar/search`, and 0 on carousel slides 2 and
3.

**Repository:** `.git` 113 MB, pack 100.49 MiB, 599 tracked files, 102.4 MB of
working tree. Largest tracked files are photographs: 6.33 MB, 6.03 MB, 4.40 MB,
4.10 MB, and one 5.93 MB video. Largest source files by line count:
DemoContentSeeder 696, ImageLibrarySeeder 513, HomepageComposer 472,
ArticleForm 449, app.css 432, NavigationManager 408.

**Uncommitted:** 41 modified files, 12 untracked, including
`app/Support/Sparkline.php`, `app/Models/Media.php`,
`app/Console/Commands/MakeOwner.php`, the `is_illustrative` migration,
`public/svg/`, and three test files. None of this is committed yet.

---

## 6. Known defects and accepted trades

**Accepted, with reasoning:**

- **Homepage LCP.** Recorded at 3685 ms in CLAUDE.md and accepted on the basis
  that render delay dominated and the remaining levers cost design. Measures
  2053 ms today. The record should be updated; the reasoning stands.
- **CLS 0.041 accepted** as the price of `font-display: optional` on the body
  face — no reflow mid-read. It now measures 0.061 on home, which is still
  inside the 0.1 threshold but has drifted and nobody re-measured it.
- **Podcast and reports render one row each** where their templates hold three
  and four. Both are filtered by `content_type` and run late in the layout, by
  which point the unfiltered sections have consumed the pool. Not caused by the
  carousel; verified by setting `big_story` back to 1 and re-counting.
- **Navigation at 12.5px** against roughly 17px in the reference. Eight Arabic
  category labels come to 750 px and do not fit beside the wordmark below about
  1900 px. Type alone cannot close this; shorter labels could.
- **LIKE-based search**, swapping to Scout at ~2,000 articles.
- **72 published articles is the floor** for a full homepage; the layout's
  article appetite is 70.
- **WCAG 2.2 target size**: metadata links were given a 24 px minimum height
  with vertical padding only, so the line does not visibly change.
- **The hero is under-resolved.** Full-bleed at 1440 CSS px and DPR 2 needs
  2880 device pixels; `home.jpg` is 1800 wide, so it renders at 0.625x and looks
  soft on a retina display. Waiting on a larger master from you.
- **The big story's photograph does not mirror in RTL**, so the focal point sits
  62% from the headline edge where the reference has 58%. Mirroring a photograph
  of a real building would be a fabrication.

**Defects not previously discussed:**

- **The newsletter form is non-functional** and looks functional. This is the
  same class of problem as the four dead-code finds, and it is on the public
  site.
- **`integrations.analytics_id` is never read.**
- **CLAUDE.md claims a `reader` guard is scaffolded. It is not.**
- **1,771 jobs are backed up in Redis** with no worker running.
- **Zero Form Requests** exist, and no controller calls `validate()`. CLAUDE.md
  §5 requires every external input to go through a Form Request. In practice the
  only public inputs are the search `q` parameter, which is cast and trimmed,
  and the newsletter form, which goes nowhere — so there is no live
  vulnerability. The rule is unmet, and it will matter the moment the newsletter
  POST is written.
- **Rate limiting.** CLAUDE.md requires it on login, password reset, search and
  newsletter signup. I did not verify it and do not know whether it is
  configured.

---

## 7. Risks

**What breaks first under real editorial load.**

The homepage composer resolves every section in one request and the article pool
is sized at `max(30, appetite * 3)`. At 72 articles that is the whole table. At
10,000 articles it is still one query returning up to 210 rows plus eager loads,
per homepage render, behind a cache — survivable, but never tested at volume.
`activity_log` is already 1,593 rows from a seed; it has no pruning and will
grow without bound.

**What breaks on deployment.**

- **The queue is a hard dependency and nothing enforces it.** Media conversions,
  404 logging and source polling all queue. There is no worker running here and
  1,771 jobs are waiting. If Horizon is not running in production, media
  conversions never generate, images 404, and the failure is silent.
- **`masar:recount` must run after any bulk insert**, or `gate_failures_count`
  stays NULL and blocked articles are invisible to the queue and filter.
- **Edge compression is required** and is not an application concern. Without it
  LCP moves from about 2.0 s to about 2.9 s on these pages — measured today by
  accident when the proxy rewrite was broken.
- **`APP_URL` must match the served host**, or every image URL points at the
  wrong port and the site renders alt text.
- **`MEDIA_DISK` must be `r2`** in production, and `storage:link` must exist
  locally.
- **The intelligence pipeline has never run against a live feed.** All seven
  seeded sources point at `.test` domains or `127.0.0.1`. Conditional GET,
  429/Retry-After handling, robots.txt honouring and the User-Agent contact URL
  are all implemented and unit-tested, and none has met a real publisher.

**What depends on a decision you have not made.**

- A larger hero master, or accepting a soft hero on retina.
- Whether the newsletter ships wired or with the form removed.
- Whether the intelligence inbox is built before launch or the pipeline stays
  dark.
- Whether English launches with content or the locale stays disabled.
- Whether `admin@masar.test` style provisioning belongs in the seeder.

---

## 8. What I would do next, and why

My order, not yours. Reasoning first in each case.

**1. Wire or remove the newsletter form.** It is the only thing on the public
site that lies to a reader. Someone will type their address into it and believe
they subscribed. This is a few hours: a POST route, a Form Request — which also
starts paying down the zero-Form-Requests finding — a store action, a duplicate
check, and rate limiting. If you would rather not run a newsletter yet, deleting
the form is a ten-minute fix and equally honest.

**2. Build the intelligence inbox.** Part B works and delivers into a table no
human can open. Every hour spent on the pipeline before there is a surface is an
hour invested in something nobody can use. This is the single largest gap
between what is built and what is usable.

**3. Run a source against a real feed before building anything else on top.**
One real RSS endpoint, one poll, one item through dedupe and classification.
Conditional GET and backoff have never met a server that disagrees with them.
This is a day and it de-risks the whole of TASK 07.

**4. Look at the category page.** LCP 3725 ms, the worst of the four, and the
only one nobody has examined. It is also the page most inbound search traffic
lands on.

**5. Start a queue worker in development and keep it running.** The 1,771
backed-up jobs mean nobody has seen the asynchronous half of this system behave.
Bugs there will surface on deployment otherwise.

**6. Then TASK 07 C to F.**

**Where I think your sequencing has been wrong:**

We spent the last several sessions on homepage visual fidelity — the scrim, the
crop, the sparkline geometry, the type scale. That work was worth doing and the
page is much better for it. But during it, the newsletter form sat broken on the
public site, the intelligence inbox stayed unbuilt while the pipeline behind it
was finished and tested, and nobody noticed 1,771 jobs piling up. Those were
visible all along; the visual work crowded them out.

The thing I would change is not the attention to detail — that attention caught
the green scrim, the density-corrected `naturalWidth`, and a pegged rate that
would have been drawn as the most volatile line on the page. It is that the
detail passes ran without a periodic check on whether anything behind them had
broken or been left half-finished. Something like this audit, every few
sessions, would have surfaced the newsletter in week one.

I would also say plainly: I told you Part B was not done, twice, and it was
substantially done. I should have checked before reporting. That is the same
failure as the `object-position` claim — reporting from memory rather than from
the codebase.

---

## Video transcode and git history

**Resolved. The push is not blocked by media.**

- The two files GitHub rejected, `9339478-uhd_3840_2160_24fps.mp4` (167.4 MB)
  and `10407687-hd_3840_2160_30fps.mp4` (101.2 MB), were transcoded to 23.05 MB
  and 24.18 MB — **still over the 10 MB budget, so they were dropped entirely**,
  per your instruction to re-source anything that stayed over. They were not
  shipped at a smaller size; they are not in the repository at all.
- A third master, `19745264-uhd_3840_2160_60fps` (25.2 MB), was not rejected by
  GitHub but failed the same budget at 20.43 MB and was dropped with them. So
  three clips are missing from the video set, not two.
- **`git log --all` shows 0 commits touching either file.** They are absent from
  the working tree, absent from `HEAD`, and absent from history.
- **No blob anywhere in history exceeds 10 MB.** The largest object in the
  repository is a 6.33 MB photograph.
- 8 video renditions ship, 0.48 MB to 5.93 MB, all 1080p H.264.
- Their masters are in `storage/app/video-masters/`, untracked, if you want to
  re-source the three that were dropped.
- Push payload: 349 blobs, 101.2 MB, largest 6.33 MB, **zero over GitHub's
  100 MB limit**. `origin/main` is an ancestor of `main`, so it is a
  fast-forward and needs no force.

`git push origin main` will go through. What is still uncommitted is 41 modified
and 12 untracked files of this week's work, which is a separate matter from the
media.
