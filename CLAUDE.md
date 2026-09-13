# MASAR (مسار) — Project Brief

> Place this file at the repository root. Claude Code reads it automatically on every session.
> Keep it updated. It is the single source of truth for how this codebase is built.

---

## 1. What we are building

MASAR is a **premium Arabic-first digital business media platform** covering
Business · Economy · Markets · Opportunities, with a primary focus on **Saudi Arabia**.

The product exists to move a reader along one journey:

```
Information  →  Understanding  →  Opportunity
```

Editorially that means every significant story answers:

```
What happened? → Why does it matter? → Who is affected? → Where is the opportunity?
```

**This is not a slogan. It is a data model.** Those questions exist as structured,
required fields on the article entity — not as free-form body text. Any design or schema
decision that makes them optional is wrong.

### What MASAR is not
- Not a breaking-news wire. We do not compete on speed.
- Not a WordPress blog with categories.
- Not a financial dashboard.
- Not an AI-to-publish pipeline. AI assists research, classification and SEO. A human writes, checks and approves.

---

## 2. Locked decisions

These were decided and are not open for re-litigation. If you believe one is wrong,
say so explicitly and wait — do not silently implement an alternative.

| Area | Decision |
|---|---|
| Geographic focus | Saudi Arabia primary; Gulf secondary; global coverage only when it affects the Saudi market |
| Launch language | Arabic (`ar`). English (`en`) architected from day one, content added later |
| URL strategy | Locale prefix always: `/ar/...`, `/en/...`. No unprefixed routes except a redirect at `/` |
| Slugs | Transliterated Latin, never URL-encoded Arabic. `/ar/saudi/foreign-property-ownership`. A slug is an **editorial decision** — the system suggests a transliteration, the editor may override. No auto-slugging package. |
| Public site | **Blade + Tailwind + Alpine.js**. Server-rendered. No SPA, no Inertia, no Node SSR |
| Admin panel | **Filament v4** (Livewire). Custom theme with MASAR brand tokens, RTL enabled |
| Admin RTL | Comes from `APP_LOCALE=ar` plus Filament's packaged Arabic translations. There is no `->direction()` or `->defaultLocale()` call in Filament v4. |
| Database | MySQL 8 |
| Cache / queue / session | Redis (required — we rely on cache tags) |
| Search | Laravel Scout + Meilisearch, with an Arabic normalisation layer |
| Hosting | VPS (Ubuntu 24.04). Nginx + **PHP-FPM 8.4** (latest patch) + Supervisor + Horizon. 8.3 is not an option: `spatie/laravel-activitylog` requires `^8.4`. Provision the server to match the pin, never the reverse. |
| Object storage | S3-compatible (Cloudflare R2) from day one. Never store media on the app server |
| Timezone | `Asia/Riyadh` |
| Currency | SAR primary, USD secondary |
| Numerals | Western Arabic numerals (0-9), never Eastern Arabic-Indic (٠-٩) |
| Fonts | Readex Pro (display) + IBM Plex Sans Arabic (body), self-hosted, subset |
| Reader accounts | Deferred. Separate `reader` guard is scaffolded but unused |
| Testing | Pest |

### Brand tokens
```
--g-950 #071A10   --g-900 #0E2A1C (primary)   --g-700 #1A4531   --g-600 #1E5E3F
--mint  #7FB69A (accent — used sparingly, never as a button fill, and never as text on a
        light ground: 2.11:1 on cream. Fine as a foreground on g-900/g-950, or as a fill
        behind dark text.)
--cream #F6F4EF (page)   --line #E2DED4   --ink #14181A
--gold  #C9A063 (semantic FILL only: badge grounds, borders, gold-on-dark. 7.47:1 on g-950.
        As text on white it measures 2.42:1 and fails — use --gold-ink instead.)
--gold-ink #8F6828 (gold as text on a light ground: 5.03:1 on white, 4.57:1 on cream)
--ink-3 #61696F (tertiary text: metadata, timestamps, captions — 5.08:1 on cream; the
        original #7C848A measured 3.46:1 and failed AA for normal text)
```

---

## 3. Architecture

**Action-oriented, not DDD, not raw MVC.**

```
Route → Middleware → Form Request (validation) → Controller (thin) → Action (one business operation)
      → Model / Query object → Event → Resource / Blade view
```

### Rules
- **Controllers stay under ~30 lines** and contain no business logic. They validate, call an Action, return a response.
- **One Action = one business operation.** `PublishArticle`, `TransitionArticleStatus`, `IngestSourceItem`.
- **Never call `$article->update(['status' => ...])` directly.** Status changes go through `TransitionArticleStatus`, which checks the enum's allowed transitions, authorises, fires an event and writes an audit entry.
- **Query objects** (`app/Queries/`) hold complex read queries. Do not let models grow past ~150 lines of scopes.
- **Events** handle side effects of publishing: cache flush, search reindex, newsletter queue, push notification, audit log. Publishing has six side effects; putting them inline makes it fragile.
- **Policies** on every editable model. Ownership matters: a writer edits their own draft, not someone else's.
- **An Action checks permission and business rules separately; a Policy may fuse them for the UI.** If the gate check lives inside the policy call, a missing summary is reported to the editor as a permissions error. Actions throw the specific failure. Policies answer the single question Filament needs to enable or disable a button.
- **Jobs** for anything slow: media conversions, source polling, campaign sends, metric aggregation.

### Folder structure
```
app/
├── Actions/{Articles,Intelligence,Homepage,Navigation,Media,Newsletter}/
├── Enums/
├── Events/            Listeners/
├── Filament/{Resources,Pages,Widgets}/
├── Http/
│   ├── Controllers/{Web,Api/V1}/
│   ├── Middleware/     Requests/     Resources/
├── Jobs/               Models/       Policies/
├── Queries/
├── Services/{Intelligence,Seo,Media,Ai}/
├── Support/
└── View/Components/
resources/views/{layouts,components,pages,partials}/
```

---

## 4. Domain model

The database is a **content graph**, not an articles table with categories.
The pivot that makes this work is a **polymorphic `entity_mentions` table**:

```
content (article | report | video | opportunity)
      ↕  entity_mentions  ↕
entity  (company | person | country | industry | market | topic)
```

This is why `/ar/companies/aramco` can aggregate every piece of content that references
Aramco across all content types with one query, instead of bespoke logic per type.

### Translation strategy (mixed, deliberately)
- **Editorial content** (articles, reports, videos): one row per locale, linked by `translation_group_id`.
  Reason: an English version of an Arabic story is rarely a literal translation — different headline,
  different angle, possibly never written at all.
- **Structured data** (categories, topics, companies, industries, menu items, settings):
  a translations table or translated JSON column. Duplicating a company as two rows breaks the graph.

### Startups
`startups` is **not** a separate table. It is `companies.type = 'startup'`.
A startup becomes a company; splitting them forces a painful migration and breaks historical links.

---

## 5. Non-negotiables

### Publish gate
Driven entirely by `config('masar.publish_gate')`. The rules below are illustrative, not
exhaustive — the config is the source of truth.

An article cannot reach `published` without:
1. A 30-second summary (2–5 points)
2. At least one source with a URL
3. A hero image **and** its alt text — every listing surface depends on it; an article without one breaks the grid
4. Having passed the fact-check stage
5. `why_it_matters` filled — this is the product promise, not a nice-to-have
6. A sponsor name when `is_sponsored` — paid placement is disclosed to the reader, every time

Enforce this in the Action **and** surface it in Filament before the button is clickable.

### The four questions are the article
The article page renders `what_happened → why_it_matters → who_is_affected → opportunity`
as the body's actual structure — icon, kicker, subhead, then that question's blocks — not
as a summary band above a second copy of the same material.

- **`article_blocks.section`** says which question a block answers. NULL means it belongs to
  none and renders in a general stream.
- **An article with any sectioned block gets the four sections. One with none gets the stream.**
  Opinion pieces and success stories are not four questions, and forcing them into that shape
  makes it a lie. `content_type` is what decides at seed time; the renderer reads the data.
- **Each section leads with its column, then its blocks.** The columns are the editorial answer
  — `why_it_matters` is a gate rule — and the blocks are the elaboration. A shape that renders
  only the blocks would let an editor satisfy the gate with text no reader ever sees.
- **A heading block leading a section becomes that section's subhead**, not a heading inside it.

### Editorial precedence
**An explicit editorial choice always beats a rule-driven one, regardless of position
on the page.** Resolve manual selections first, then fill automatic slots around them.
A rule must never consume something an editor pinned.

Applies wherever curation meets automation: the homepage composer, newsletters, push
notifications, and the intelligence inbox.

### Copyright and sourcing
- Raw text ingested from monitored sources is **internal only**. It must never render on the public site.
- Everything published is original MASAR writing with attribution and a link to the source.
- Never fabricate a source, a quote, a statistic, or a person.

### Security
- Never commit secrets. No API keys, tokens or passwords in code, migrations, seeders or tests.
- `$fillable` explicitly listed on every model. Never `$guarded = []`.
- Validate every external input through a Form Request.
- File uploads: verify real MIME type, not the extension. Re-encode images.
- 2FA required for any user with publish rights.
- Rate limit login, password reset, search, newsletter signup.

### Performance
- **Denormalised counters count published rows only.** A topic page advertising 12 articles and rendering 3 is worse than showing no count at all.
- **A filter that can't be expressed in SQL must not scan the table.** Narrow in SQL first, and denormalise the predicate once volume justifies it.
- **Never write a query inside a loop.** Use eager loading. If you add a relation to a listing view, add it to the `with()`.
- Article lists must not load body blocks or revisions.
- Composite indexes on `(locale, status, published_at)` and `(category_id, status, published_at)`.
- Unique index on `(locale, slug)`, never on `slug` alone.
- View counts go to Redis and flush periodically. Never `increment()` on every request.
- **A long page defers what is off-screen.** `content-visibility: auto` on the front page's lower sections took it from 30 images and 2 MB to 5 images and 560 KB. It also blinds contrast checkers to unpainted subtrees, so anything carrying it declares an explicit `background-color`.

### Market figures
There is no feed and no licence. **Every figure the ticker, chart, instrument grid,
intelligence pulse and data grid render is typed in by an editor**, lives in `market.*`
settings, and carries a date and a source.

- **`market.as_of` gates the whole set.** Undated figures do not render at all — a number
  with no "as of" is one a reader will assume is live.
- **The empty state is the honest state**, not an edge case. Every accessor can return
  nothing and every caller renders a labelled placeholder instead.
- **No figure is ever hardcoded in a Blade file.** Enforced by test: the suite greps the
  views for thousands-separated numbers and signed percentages sitting in markup.
- The chart is drawn **without a value axis**, because the points are an editor-entered
  shape and an axis would present them as measured.
- **A direction glyph accompanies every colour.** Red/green alone fails WCAG 1.4.1 and is
  the pairing most readers cannot distinguish.

### Imagery and video
- **A photograph the site can use is a Media Library record, not a file on disk.** The publish gate reads `articles.hero_media_id`; a JPEG in `public/` is invisible to it.
- **Alt text describes the picture, in Arabic.** Never the filename, never "صورة". It is a gate rule and an accessibility requirement, and a filename fails both.
- **Credit every photograph**, using the photographer's published handle rather than an invented Arabic spelling of a real person's name.
- **No hero repeats inside one homepage section.** An image may appear many times across the site; twice in one grid reads as broken even when nothing is.
- **Forced `direction: ltr` is for values that are only ever Latin** — dates, tickers, emails, URLs. An editor-entered figure can be `+24%` or `31 مليار ريال`; wrap those in `<bdi>`, which isolates without imposing a direction. `ltr-isolate` on the second kind reverses it.
- **Seven card weights, not one card at seven sizes.** `card-article` · `card-story` · `card-lead` · `card-row` · `card-rank` · `card-overlay` · plus the big story. The variation between them is most of what makes a page look edited rather than generated; collapsing any two is what makes it read flat. Enforced by test.
- **Aspect ratio is a property of the box, not of the photograph.** The six crops live in `App\Support\AspectRatio` and in one CSS block: hero/featured 16:9 · article inline 16:8 · card thumbs 16:10 · portrait 4:5 · square 1:1 · magazine cover 3:4.1. A template names the role; no template names a number.
- **A srcset offers only candidates the box can use.** A 74px rail thumbnail listing an 1800px file will be served it on a dense screen, and the markup is paid on every card whether or not it is chosen. `MediaConversions::srcsetWidths()` narrows by scale: `thumb` (200/400), `card` (400/900), `full`.
- **Every conversion ships WebP and JPEG.** A `<picture>` with one format is not a fallback. Social cards use the JPEG: several scrapers reject WebP and fall back to no image rather than to the original.
- **Self-hosted video:** `<video>` with `preload="none"`, `playsinline`, a poster that is a real media conversion, and explicit dimensions. Autoplay is always muted. A clip over `masar.media.max_video_kb` is reported, not shipped.
- **YouTube is a facade.** Poster plus click-to-load, `youtube-nocookie`, and **no third-party request of any kind before a click** — the poster is ours, not `i.ytimg.com`.
- **AI-generated imagery does not ship as reportage.** A rendered market figure, a real company's logo on a facility that does not exist, or a national flag redrawn by a model are all fabrications, whatever the prompt said.
- **One exception, and it is enforced by a column.** A generated image may be the decorative backdrop behind the big story, where it carries mood rather than documenting a place. It is flagged `media.is_illustrative`, owned by the homepage layout and never by an article, credited «صورة تعبيرية» so the admin shows what it is, and **its alt text names no city** — the moment a generated skyline is captioned as a real place it becomes a claim the pixels do not support. A model guard rejects illustrative media on any article collection, so the rule survives whoever joins next.

### RTL and Arabic
- Use Tailwind **logical properties** (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) from the first line. Never `ml-`/`mr-`/`pl-`/`pr-`.
- Force Western numerals: `font-variant-numeric: lining-nums`.
- Wrap inline Latin text (company names, tickers) with `dir="ltr"` + `unicode-bidi: isolate`.
- Arabic body text needs larger size and looser leading than Latin: 18px / 1.95.
- **`font-display` is a decision per face, not a global default.** The body face is `optional` — a reader who misses the 100ms window reads the fallback for the whole visit and never watches a paragraph reflow, which is the failure a long Arabic article makes most visible. The display face keeps `swap`: a headline arriving late is visible, and one never arriving is worse. Both faces are preloaded — an `optional` face that misses its 100ms window is dropped for the whole visit, and the preload is what gets it inside that window (measured: removing it cost ~200ms of FCP and bought nothing back).

### Accessibility
Semantic HTML, visible focus states, alt text required on every image,
keyboard navigation, `prefers-reduced-motion` respected.

---

## 6. Conventions

- Migrations: one table per file, descriptive names, always with `down()`.
- **Exception:** a `*_translations` table ships in the same migration as its parent entity. It has no independent lifecycle, so splitting them adds files without adding clarity.
- Every model gets a **factory**. Every factory gets used in tests.
- Enums are backed string enums in `app/Enums/`. Cast them on the model.
- **Never use native MySQL `ENUM` columns.** Store as `string(32)`. The PHP enum is the guard; a new case must never require a table rewrite.
- **Add an index only if no existing index already covers the column as a left prefix.** A redundant index is pure write cost. Check the existing composite indexes before adding one.
- Every model registered with `LogsActivity` must also be in the morph map. `enforceMorphMap()` rejects unregistered models at write time.
- **Constrain every route parameter at the router, not only in middleware.** An unconstrained `{locale}` or `{category}` will swallow `sitemap.xml`, `rss.xml` and every sibling route.
- **Filament closure parameters must use Filament's own names** (`$query`, `$record`, `$state`, `$livewire`, `$get`, `$set`). `evaluate()` resolves by name first; a mismatched name silently resolves from the container instead of failing. Enforced by test.
- **An accent used as a foreground must state its ground.** `text-mint` and plain `text-gold` fail on cream (2.11 and 2.42) and pass on g-950 (7.78). A component using either carries a `{{-- contrast-safe: … --}}` line saying which ground it sits on; an unexplained use fails the suite. The check is not a ban and not a list of filenames.
- **A CSS override must target a class the framework actually renders.** Dead CSS fails silently — no error, no warning, and a theme that looks finished. The admin's active sidebar item sat at 1.05:1 for the life of the project because the override named `.fi-sidebar-item-active .fi-sidebar-item-button` and Filament renders `.fi-active` and `.fi-sidebar-item-btn`. Read the computed style off the rendered page, not the stylesheet.
- **An ARIA role replaces an element's implicit role; it does not add to it.** `role="tabpanel"` on a `<ul>` is no longer a list, and a screen reader stops announcing how many items it holds. Wrap the element, never override it — and the same goes for `role="button"` on an `<a>`, `role="list"` on a `<div>` that has `<li>` children, and every other "just add the role" fix.
- **Media lives on the disk named by `MEDIA_DISK`.** Local dev resolves it to `media` under `storage/app/public`; production sets `r2`. No code anywhere names a disk.
- **A section must not resolve more items than its template renders.** `HomepageSectionType::defaultLimit()` is the design's count, not a generous ceiling — anything past it is content an editor placed and no reader sees.
- Route names: `web.{locale}.{section}.{action}` and `api.v1.{resource}.{action}`.
- Blade components live in `resources/views/components/` and are the design system. **Do not invent a new component if an existing one fits.**
- Commits: imperative mood, scoped, one concern per commit. `feat(articles): add publish gate validation`.
- Comments explain *why*, not *what*. Arabic comments are fine for editorial-logic explanations.

---

## 7. Definition of done

**When a component's failure mode is silence, test the mechanism, not only the
outcome.** Four separate pieces of this codebase looked alive and were not —
`$exceptions->report()` never firing for `NotFoundHttpException`, a Filament
closure parameter named `$q` resolving from the container instead of the query,
a block builder that loaded empty and discarded what was typed into it, and a
SimHash that put a 64-bit value through a float and fingerprinted unrelated
headlines as identical. Every one produced plausible output and passed review.
Asserting the result is not enough when the broken version produces a result
that looks correct: assert that the thing actually ran, on the input you think
it ran on.



A task is not complete until all of these are true:

- [ ] Migrations run cleanly forward **and** roll back
- [ ] Models have relationships, casts, explicit `$fillable`
- [ ] Factories exist and produce realistic Arabic content (not `lorem ipsum`)
- [ ] Feature tests cover the happy path and the main failure path
- [ ] `php artisan test` passes
- [ ] No N+1 queries in any view you touched (check with Debugbar or `DB::listen`)
- [ ] No secrets, no hardcoded credentials
- [ ] RTL verified if UI was touched
- [ ] You reported what you changed, why, and anything you decided that wasn't specified

---

## 8. How to work with me

- **Read this file before starting.** Then read the task file.
- If a requirement is ambiguous, **ask one clear question** rather than guessing and building the wrong thing.
- If you need to deviate from a locked decision, **say so and wait for approval**. Do not silently change architecture.
- Prefer small, verifiable increments over one large change.
- Do not create files that were not asked for. No README spam, no example files, no scaffolding "for later".
- When you finish, output: files created/modified, commands to run, and what success looks like.


---

## 9. Local environment

| Service | Setting |
|---|---|
| PHP runtime | 8.5.2 installed locally |
| **PHP platform target** | **8.4.1** — pinned in `composer.json` under `config.platform.php`. Not `8.4`, which Composer reads as exactly 8.4.0 and which the installed Symfony 8.1 set rejects. This guarantees nothing requiring 8.5 can install. **The production server must run 8.4.1 or later.** |
| MySQL | **8.x on port 3307**. XAMPP's MariaDB 10.4 on 3306 is not used — it has no native JSON type. |
| Test database | `masar_test` on MySQL, **not** SQLite. Native types and composite indexes do not behave identically on SQLite. |
| Redis | running, `phpredis` extension. Cache, session and queue all point at it. Required — we rely on cache tags. |
| Morph map | Enforced via `Relation::enforceMorphMap()` in `AppServiceProvider`. Stored values are short keys (`'company'`), never class names. Morph type columns are `string(100)`. |
| `gate_failures_count` | **NULL means never evaluated, not publishable.** It is written only where the publish gate already runs — a save or a transition — so rows created outside those paths (factories, seeders, any future import) stay NULL and are *invisible to the blocked-by-gate queue and filter*. An imported archive would silently show as unblocked until `masar:recount` fills the column. Run `masar:recount` after any bulk insert. |
| Search | LIKE-based over a normalised expression, not an index. Arabic folding happens in `ArabicNormaliser` on both the query and the stored text. **Swap to Scout/Meilisearch inside `SearchArticlesQuery` when published articles pass ~2,000** — the caller does not change. |
| **Edge compression is required** | The Core Web Vitals targets are met *only* with gzip/brotli at the edge. Measured on a mobile 4G profile: with compression LCP 1.8–2.2s (passes); without it 2.7–4.1s (fails). Nginx must compress `text/*`, `application/javascript`, `application/json`, `image/svg+xml`. This is a deployment requirement, not an application concern — do not add compression middleware to Laravel to paper over a misconfigured edge. |
| `APP_URL` | **`http://127.0.0.1:8000`**, matching `php artisan serve`. Media URLs are absolute and built from it, so leaving it at `http://localhost` points every image at XAMPP's Apache on port 80 and the whole site renders with alt text where the photographs should be. |
| **Homepage LCP: 3685 ms. Known and accepted.** | Measured on Lighthouse's mobile slow-4G profile with compression at the edge. The LCP element is the big-story hero photograph — `div.grid > div.relative > picture.block > img.h-full` — and the phases are **TTFB 12% · Load Delay 29% · Load Time 2% · Render Delay 57%**. Load Time is 2%: the image is not the problem. FCP is 2270 ms and is LCP's floor, because 512 KB on that profile is ~2.6 s of transfer before anything paints. Every free lever has been pulled — narrowed srcset ladders, `font-display` per face, a metric-matched fallback, deferred off-screen paint. All eight font faces were checked and are genuinely used. **The remaining levers all cost design: fewer font weights, a smaller hero crop, or fewer sections. Nineteen sections with real photography is the product — do not cut sections to move this number.** Home CLS is 0.041, accepted as a trade for `font-display: optional` on the body face (no reflow mid-read) against a previous 0.013. |
| Media storage | `MEDIA_DISK` selects it. Local is `media` (`storage/app/public/media`, served through `storage:link`); production is `r2` with the `R2_*` credentials from the environment. `config/media-library.php` reads that one key, so nothing else in the app names a disk. |
| Demo imagery | `ImageLibrarySeeder` turns `public/images/` into real media records. It normalises each source to a 2400px master once and generates each conversion once per **image**, not once per article — the same 28 photographs behind 130 articles is 704 identical conversions otherwise, which measured at ten minutes of `migrate:fresh --seed` against 4 seconds now. `MediaConversions::withoutGenerating()` is what makes that safe; a caller that uses it and forgets to write the files leaves records whose conversions 404. |
| **No media master in the repository** | GitHub rejects any file over 100 MB, and two 4K stock clips — 167 MB and 101 MB — did exactly that. Deleting them from the working tree does not fix it: the blob is already in the pack and the push fails on the same object. `public/images/` holds **web-delivery renditions only**, named `<id>-web-<width>x<height>.mp4` so the name states what the file is, 1080p on the long edge, H.264, and **under `masar.media.max_video_kb`** (10 MB). Masters live in `storage/app/video-masters/`, which is untracked. `.gitignore` cannot express a size rule, so the size rule is a test: `tests/Feature/RepositoryWeightTest.php` fails on any tracked file over the budget and on any tracked name with a master's shape. A clip that is still over budget after transcoding is not committed at all — it is re-sourced. |
| Demo video | `VideoLibrarySeeder` transcodes each source to 1080p H.264 once into `storage/app/seed-video` and caches it there, so the first run on a fresh checkout takes minutes and every later run takes seconds. It needs `ffmpeg` on `PATH` and skips with a warning without it. |
| Looking at the page | **After any visual change, open the rendered page and look at it.** A suite cannot tell you every photograph on the site is missing, that a section is blank, or that an Arabic figure is rendering backwards — all three shipped past green tests. Screenshot the app, not the prototype. `content-visibility: auto` means a full-page capture of off-screen sections comes back blank, so scroll each slice into the viewport and capture that. |
| Test-suite concurrency | Two runs against the single `masar_test` schema truncate each other via `RefreshDatabase`, producing scattered failures that look like real bugs. `Tests\TestCase` takes an exclusive `flock` on `masar-test-suite.lock` and refuses the second run with an explanatory message. The lock is held by the OS, so a killed run never leaves a stale one. Opt out with `MASAR_ALLOW_CONCURRENT_TESTS=1`; Pest `--parallel` is detected and exempt because it gives each process its own schema. |

**Never** change these without saying so. A silent environment change is the hardest
class of bug to find later.
