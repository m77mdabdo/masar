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
--mint  #7FB69A (accent — used sparingly, never as a button fill)
--cream #F6F4EF (page)   --line #E2DED4   --ink #14181A
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

### RTL and Arabic
- Use Tailwind **logical properties** (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) from the first line. Never `ml-`/`mr-`/`pl-`/`pr-`.
- Force Western numerals: `font-variant-numeric: lining-nums`.
- Wrap inline Latin text (company names, tickers) with `dir="ltr"` + `unicode-bidi: isolate`.
- Arabic body text needs larger size and looser leading than Latin: 18px / 1.95.

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
- Route names: `web.{locale}.{section}.{action}` and `api.v1.{resource}.{action}`.
- Blade components live in `resources/views/components/` and are the design system. **Do not invent a new component if an existing one fits.**
- Commits: imperative mood, scoped, one concern per commit. `feat(articles): add publish gate validation`.
- Comments explain *why*, not *what*. Arabic comments are fine for editorial-logic explanations.

---

## 7. Definition of done

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
| Test-suite concurrency | Two runs against the single `masar_test` schema truncate each other via `RefreshDatabase`, producing scattered failures that look like real bugs. `Tests\TestCase` takes an exclusive `flock` on `masar-test-suite.lock` and refuses the second run with an explanatory message. The lock is held by the OS, so a killed run never leaves a stale one. Opt out with `MASAR_ALLOW_CONCURRENT_TESTS=1`; Pest `--parallel` is detected and exempt because it gives each process its own schema. |

**Never** change these without saying so. A silent environment change is the hardest
class of bug to find later.
