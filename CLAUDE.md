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
| Languages | **Six editions** (owner's ruling, 2026-10-28, superseding "Arabic at launch, English later"): `ar` · `en` · `fr` · `hi` · `ur-pk` · `zh-hans`. The code is the URL segment and the stored `locale` value; the BCP 47 tag (`ur-PK`, `zh-Hans`) goes in `<html lang>` and hreflang. `App\Support\Locales` is the only reader of `config('masar.locales')`. Arabic and Urdu run RTL; Urdu is Nastaliq, never naskh, and never Hindi. **All six editions are public** (owner's ruling, 2026-10-29, superseding "switched on after review"): the interface is served while its translation review is pending. Three controls stay separate — public availability (`MASAR_LOCALES_ENABLED`, default all six), interface review (`translation_reviews`, see §5), and editorial publication (the workflow and gate). An edition is never switched off for having no stories. **Machine-assisted translations publish on the owner's authority, never as reviewed** (owner's ruling, 2026-10-29, superseding "machine translation produces drafts, never publications"): every story ships in all six editions as `translation_status = owner_authorized`, and native-speaker review stays a separate, recorded state. No outside translation service is used (owner's ruling): the provider seam ships with a null driver. |
| URL strategy | Locale prefix always: `/ar/...`, `/en/...`. No unprefixed routes except a redirect at `/` |
| Slugs | Transliterated Latin, never URL-encoded Arabic. `/ar/saudi/foreign-property-ownership`. A slug is an **editorial decision** — the system suggests a transliteration, the editor may override. No auto-slugging package. |
| Public site | **Blade + Tailwind + Alpine.js**. Server-rendered. No SPA, no Inertia, no Node SSR |
| Admin panel | **Filament v4** (Livewire). Custom theme with MASAR brand tokens, RTL enabled |
| Admin RTL | Comes from the panel locale plus Filament's packaged translations — the signed-in user's `users.locale`, defaulting to `ar` (2026-10-28). There is no `->direction()` or `->defaultLocale()` call in Filament v4. |
| Database | MySQL 8 |
| Cache / queue / session | Redis (required — we rely on cache tags) |
| Search | Laravel Scout + Meilisearch, with an Arabic normalisation layer |
| Hosting | VPS (Ubuntu 24.04). Nginx + **PHP-FPM 8.4** (latest patch) + Supervisor + Horizon (installed). The dashboard is gated on `viewHorizon` → `super_admin` in **every** environment: Horizon's packaged gate is `Gate::check(...) || app()->environment('local')`, which opens it to anyone who can reach the port on a dev machine, and job payloads here contain subscriber email addresses. `HorizonServiceProvider` replaces the callback to drop that escape hatch. 8.3 is not an option: `spatie/laravel-activitylog` requires `^8.4`. Provision the server to match the pin, never the reverse. |
| Object storage | S3-compatible (Cloudflare R2) from day one. Never store media on the app server |
| Timezone | `Asia/Riyadh` |
| Currency | SAR primary, USD secondary |
| Numerals | Western Arabic numerals (0-9), never Eastern Arabic-Indic (٠-٩) |
| Fonts | Readex Pro (display) + IBM Plex Sans Arabic (body), self-hosted, subset, for Arabic and Latin. Nastaliq, Devanagari and Han use the reader's system face by script (`html[data-script]` in `app.css`); nothing is fetched from outside (owner's ruling, 2026-10-28). |
| Reader accounts | Deferred and **not scaffolded**. There is no `reader` guard in `config/auth.php` — this row previously claimed one existed, and an audit found it did not. When reader accounts are built, the guard is the first thing to add |
| Testing | Pest |

### Brand tokens
```
--g-950 #001D17   --g-900 #002E24 (primary)   --g-700 #0F4A3B   --g-600 #14614C
--mint  #7FB69A (accent — used sparingly, never as a button fill, and never as text on a
        light ground: 2.07:1 on cream. Fine as a foreground on g-900/g-950, or as a fill
        behind dark text.)
--cream #F5F2E9 (page, warm ivory)   --line #E4DFD1   --ink #0D1C17 (very dark green,
        never pure black — 15.69:1 on cream)
--gold  #C9A063 (semantic FILL only: badge grounds, borders, gold-on-dark. 7.32:1 on g-950.
        As text on cream it measures 2.16:1 and fails — use --gold-ink instead.)
--gold-ink #8A6526 (gold as text on a light ground: 5.29:1 on white, 4.73:1 on cream)
--ink-3 #5E6A63 (tertiary text: metadata, timestamps, captions — 5.05:1 on cream)
--mint-2 #A8D4BC · --coral #E98880 (market surfaces, foregrounds on the dark band only:
        10.78:1 and 6.98:1 on g-950)
--g-850 #0A3D30 (one step lighter than the band, for the cell that terminates it)
```

Every ratio above is measured, and the suite re-measures them. Change a token and
the contrast test tells you what broke.

**Homepage hero, text over the photograph** — measured 2026-10-27 off the rendered
pixels behind each element (text hidden), against the *lightest* pixel under its
worst line box, worst case over both lead-slide backdrops, with the dark-green scrim
(owner's ruling: `--color-g-950`, not black). Re-measured 2026-10-28 at the taller
stage (580 / 664 / 720 / 860 at 1024 / 1280 / 1440 / 1920) under the compact
chrome — 30px topbar carrying the demo disclosure, 54px masthead, 50px ticker; the
1280 row re-measured again at 664. Re-measured again 2026-10-28 after the questions
column lost its full-height band, and again after the headline side lost its band
too (below). This table is slide one; slides two and three are below:

| width | headline | mint word | standfirst | byline | step label | step body | numerals 01–03 |
|---|---|---|---|---|---|---|---|
| 375 | 11.25 | 5.94 | 6.92 | 6.18 | 12.48 | 7.49 | 6.31 |
| 430 | 10.89 | 5.26 | 6.83 | 6.19 | 12.87 | 6.53 | 5.88 |
| 768 | 10.64 | 5.14 | 7.35 | 5.91 | 11.68 | 6.37 | 6.06 |
| 1024 | 10.12 | 5.43 | 6.61 | 6.44 | 13.60 | 6.29 | 6.00 |
| 1280 | 10.12 | 5.13 | 6.70 | 6.25 | 14.13 | 6.35 | 6.03 |
| 1440 | 9.97 | 5.13 | 6.85 | 6.18 | 13.54 | 6.29 | 6.01 |
| 1920 | 10.22 | 4.93 | 6.74 | 6.16 | 13.09 | 6.55 | 5.75 |

Green is lighter than black at equal opacity, so the green scrim needs more of it:
at black's stops the numerals fell to 3.92:1. Before the October passes the scrim
was sized in percentages of a full-bleed section: headline 2.07 and mint word 1.0 at
1920, numerals 1.31 at 1440. Earlier figures in this paragraph ("headline ≥ 11.11",
then a black-scrim table) are superseded. These are not in the suite — the ground is
a photograph — so re-measure if any local scrim below moves, or a backdrop is
added.

**No slide has a band any more** (owner's ruling, 2026-10-28; `.hero-scrim` and
`.steps-scrim` are gone from the homepage, and `.hero-scrim` remains on the article
hero only). Each text block carries its own blurred local scrim (`.text-scrim` 0.85,
`.kicker-scrim` 0.65, `.questions-scrim`), so the photograph stays visible around
the text and no gradient ends in a line. The short unclamped lines carry a close
`text-shadow` halo; **clamped text never does**. `line-clamp` clips its own box,
and a clipped halo renders as a hard dark rectangle behind the headline, the
standfirst and every answer. That shipped once and was caught at 2× zoom. Photo
slides, same method, both backdrops:

| width | kicker | headline | mint word | standfirst | byline | button | step label | step body | numerals |
|---|---|---|---|---|---|---|---|---|---|
| 375 | 15.08 | 10.10 | 4.90 | 6.91 | 6.00 | 14.32 | 11.03 | 6.41 | 5.57 |
| 430 | 14.88 | 9.85 | 4.91 | 6.84 | 6.06 | 14.10 | 11.19 | 6.28 | 5.30 |
| 768 | 15.07 | 9.76 | 4.90 | 6.92 | 5.98 | 13.13 | 8.80 | 6.00 | 5.44 |
| 1024 | 7.69 | 10.01 | 4.91 | 6.49 | 5.81 | 11.53 | 9.71 | 6.59 | 4.93 |
| 1280 | 7.96 | 10.00 | 4.91 | 6.49 | 5.71 | 11.71 | 10.40 | 6.68 | 5.61 |
| 1440 | 7.23 | 10.13 | 4.91 | 6.50 | 5.87 | 11.74 | 9.98 | 6.65 | 5.52 |
| 1920 | 5.75 | 9.56 | 4.93 | 6.51 | 5.71 | 11.52 | 9.84 | 6.86 | 5.13 |

`.questions-scrim` is a blurred field at 0.90 where the numerals sit, easing to 0.70
under the text, and the numerals are solid `--gold`. At 1440 the photograph keeps
~71% of its brightness behind the headline column (55% under the old band on slide
one) and ~60% behind the questions (32–51%). Tightest figure: the mint word, 4.90.
Lighter fields fail: without a halo on the headline, 0.80 put the mint word at 4.13,
and 0.65 flat behind the questions put a numeral at 2.50. Text-shadow alone, with no
field, measured 1.0–1.7 on the daylight photographs. Re-measure if any opacity, the
halo or `--q-*` move. Re-measure if any local scrim's inset, blur or
opacity moves.

**Fold budget (measured 2026-10-29, Arabic, launch baseline = demo banner off).** The
banner occupies the topbar's fixed 30px slot, so on and off measured identical at
every size. Above the hero: topbar 31 · masthead 55 · ticker 122 (phones, wraps to
two columns × three rows) / 86 (768) / 50 (≥1024).

| device | hero top / height | first question (top–bottom) | result |
|---|---|---|---|
| 375×667 | 208 / 796 | 620–690, fold 667 | starts 47 above, last 23px below — within the 100px allowance |
| 390×844 | 208 / 770 | 594–663 | fully visible |
| 430×932 | 208 / 770 | 594–663 | fully visible |
| 768×1024 | 172 / 738 | 593–640 | fully visible |
| 1440×788 (real window) | 136 / 676 | 310–379 | visible; CTA 20 and controls 12 above the fold |
| 1440×900 | 136 / 720 | 332–401 | visible; CTA 88 above |
| 844×390 landscape | 172 / 738 | 593–640 | 203 below the fold (no rule set) |

Rule: at 390×844 and up the first question is fully visible; at 375×667 it starts
within 100px of the fold. Re-measure if the ticker, masthead or hero copy move.
Moving the first question above the CTA on phones (grid `order`) was evaluated and
not implemented, pending a decision.

**The big story moves (owner's rulings, Tasks 2.10 and 2.11).** Each photograph drifts
from `scale(1.04)` to `scale(1.0)`, then cross-fades to the next.
- **Timing.** Dwell **5s**. That is the owner's floor: under it the hero reads as a
  billboard rather than a publication. Lowering it is the owner's call, and the
  suite fails below 5000. The fade is **480ms**, linear, inside the owner's
  450–500ms, and the incoming slide fades in over the outgoing one, which stays
  opaque beneath it. The drift runs dwell + fade (5.48s) so the outgoing picture is
  still settling under the incoming one. It is linear, never below 1.0, and its
  origin is the slide's focal point. A test ties the CSS duration to the JS
  timings. Task 2.10 ran 8s / 800ms / 1.06.
- **Compositor only.** It animates `transform` on the image layer only. Traced:
  zero main-thread paints during the drift. The only paints come at a slide
  change, when the incoming layer is first rasterised. No `compositeFailed`, so
  no `will-change`.
- **Contrast across the motion.** It holds at every point, not only on one
  frame. Measured at the drift's start (1.04), middle (1.02) and end (1.0), plus
  two cross-fade frames: the incoming text over the outgoing picture (α 0), and
  over a 50/50 blend (α 0.5). All three slides, both backdrops; worst element per
  width (2026-10-28):

  | width | start 1.04 | mid 1.02 | end 1.0 | fade α 0 | fade α 0.5 |
  |---|---|---|---|---|---|
  | 375 | 4.90 | 4.90 | 4.90 | 4.90 | 5.12 |
  | 430 | 4.90 | 4.90 | 4.91 | 4.91 | 5.12 |
  | 768 | 4.90 | 4.90 | 4.90 | 4.90 | 5.11 |
  | 1024 | 4.76 | 4.91 | 4.91 | 4.89 | 4.93 |
  | 1280 | 4.91 | 4.91 | 4.91 | 4.89 | 5.20 |
  | 1440 | 4.93 | 4.94 | 4.91 | 4.92 | 5.13 |
  | 1920 | 4.94 | 4.93 | 4.93 | 4.90 | 5.06 |

  The floor is 4.62 (re-measured 2026-10-29 after the per-image focal points;
  it was 4.76 at the old 50% crop): a numeral at 1024 on slide two, at 1.04. That is with
  `--q-num` back at 0.90. Task 2.10 had raised it to 0.92 because at 1.06 the same
  numeral measured 4.55. The smaller excursion removed the need, so it was
  reverted.
- **How much of the picture a reader gets.** A 2:1 stage cannot show all of a
  1.775:1 photograph. `cover` fills the stage and cuts the overflow. The only ways
  to show the whole frame are letterboxing, which ends the full bleed, or a stage
  shaped like the source, which makes the hero taller. The shares below are of
  the source frame visible at rest, at 1.0. At the drift's start, 1.04, every
  figure is 92.5% of these (1/1.04²).

  | width | stage | plain backdrop 1.775 | dusk backdrop 2.278 | article photos 1.5 |
  |---|---|---|---|---|
  | 375 | 375×796 | 26.5% | 20.7% | 31.4% |
  | 430 | 430×770 | 31.5% | 24.5% | 37.2% |
  | 768 | 768×738 | 58.6% | 45.7% | 69.4% |
  | 1024 | 1024×580 | 99.5% | 77.5% | 85.0% |
  | 1280 | 1280×664 | 92.1% | 84.6% | 77.8% |
  | 1440 | 1440×720 | 88.8% | 87.8% | 75.0% |
  | 1920 | 1920×860 | 79.5% | 98.0% | 67.2% |

  Below 1024 the stage is portrait and the sides are cut. From 1280 up, the
  article photographs lose the top and bottom, 40:60, from `50% 40%`. The
  backdrops' fix is a specified job in `DEFERRED.md`.
- **Framing.** Checked at 1.04 and 1.0, at all seven widths, for all four images.
  - Both backdrops: the arched tower is whole at every width and scale. On a
    phone at 1.04 the plain backdrop keeps ~1% of the frame to spare on the
    tower's right.
  - Slide 2 (`pexels-aburhman187-29859597`): the bicycles and the central towers
    are intact everywhere. The two tall flanking towers are outside the band on
    phones. At 768 the right-hand one is clipped at its right edge, at rest and
    at 1.04.
  - Slide 3 (`pexels-tima-miroshnichenko-5717315`): the table, window and plant
    are intact everywhere. The notebook is half out of the band at 375 and 430.

  Article photographs all take `50% 40%` from `big-story.blade.php`; there is no
  per-image focal point. The range is not to be widened without redoing this
  check.
- **LCP.** The carousel is its own Vite entry (`resources/js/big-story.js`), not
  part of `app.js`, which builds byte-identical. A loader of a few lines inside the
  component imports it only after the load event, the hero image's `decode()` and
  two animation frames. The load event alone was too early: it fired at 243ms
  and the hero painted at 262ms. Until the chunk arrives the section is
  `x-ignore`d and shows the server frame: slide one, still, at 1.04.
  - The pause control and every binding come from the chunk, so the page carries
    about 310 B gzipped more HTML than before.
  - Lighthouse (simulate), static hero (`e2faa6f`) against the moving one, eleven
    runs per backdrop each, same harness, same day (2026-10-28). Medians: plain
    3234 → 3233, dusk 3681 → 3681. No regression.
  - **The figures recorded under Task 2.10 measured a carousel that never ran.**
    `@js()` writes the chunk URL as `http:\/\/127…`, the local gzip proxy rewrote
    only the unescaped form, and the browser refused the cross-origin module. In
    those runs the request had status −1. Any proxy used for these measurements
    must rewrite both forms, and the chunk's status must be confirmed as 200 in
    the Lighthouse JSON before a figure is quoted.
- **Reduced motion.** Nothing applies: no drift, no advance, and no pause control
  (there is nothing to stop). Measured: 0 pixels change in 10s. Prev and next
  remain.
- **Pausing and taking control.**
  - Hover and keyboard focus anywhere in the section freeze both the drift and
    the timer, via `animation-play-state`. Removing the class would snap the
    picture to its rest scale.
  - Prev or next stops the advance for the session (`sessionStorage`) and does
    not give it back. Only the reader pressing play resumes it.
  - The live region is `off` while it advances on its own and `polite` once the
    reader navigates, so an auto-advance announces nothing.
  - The pause button's label states what it will do, and a status line states
    what it did.
- **Data.** Only the next slide is fetched, half a dwell in (2.5s). Across three
  slide changes no media URL was requested twice. On the Lighthouse mobile
  profile (1.6 Mbps, 150ms RTT, 4× CPU), the next slide is decoded before its
  fade at every width. The tightest case is slide 2 at 1440 and 1920, a 262 KB
  file decoded ~990ms before its fade. At 400 kbps it misses the 5s mark, and
  the fade waits for the decode instead: it began at 8.3s, 13ms after the image
  decoded. A slow connection stretches the dwell. It never fades into an empty
  frame.

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
- **A queued job must not carry a model it may outlive.** A serialised model is stored as an id and re-fetched by the worker, so any job that outlives its row dies on `ModelNotFoundException` — and the queue is not rebuilt by `migrate:fresh`, so every job already in Redis is orphaned the moment the schema is reseeded. The first drain of a real backlog produced 45 of exactly these. Compute what the job needs while the row is certainly there and queue the scalars: `FlushArticleCaches` resolves its cache tags inline and hands `FlushCacheTags` a list of strings.
- **`$deleteWhenMissingModels` does not work on a queued listener.** The queue reflects on the resolved job class, which for a listener is Laravel's `CallQueuedListener`, never yours — so the flag is read off the wrong class and silently ignored. It is a guard for queued *jobs* only. This is why the rule above says remove the model rather than flag it.

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
- **The sources this product most needs to watch do not publish machine-readable feeds.** Measured, not assumed: SPA has no RSS at any path (every feed URL 307s to `/ar`), Tadawul's own `robots.txt` returns 403, CMA redirects, and GASTAT, the Ministry of Finance and Vision 2030 have no feed at any conventional path or in autodiscovery. **Coverage of Saudi regulators is therefore a manual editorial function, not an automated one** — a person with a browser and a checklist. The intelligence pipeline covers what publishes feeds, which today means newspapers and foreign institutions. Do not let a later reader assume the inbox watches the regulators; it does not, and building on that assumption would put a hole in exactly the coverage the product exists for.
- **The crawler's User-Agent must name a URL and an address that resolve. A contact line that goes nowhere is worse than none — it claims accountability we haven't provided.** Enforced by a gate, not a note: `masar.intelligence.polling_enabled` defaults to false and `routes/console.php` does not register the poll schedule at all while it is off, so `schedule:list` shows the truth. Manual runs stay available for development and say out loud that the schedule is off. We sent `+https://masar.sa/bot` and `contact@masar.sa` to two real publishers before either existed; a publisher wanting us to slow down had nowhere to write.
- Raw text ingested from monitored sources is **internal only**. It must never render on the public site.
- Everything published is original MASAR writing with attribution and a link to the source.
- Never fabricate a source, a quote, a statistic, or a person.

### What the site claims about itself
- **A page may not describe a system guarantee the code does not enforce. Either the code enforces it or the page says what we do, not what the system prevents.** A reader cannot inspect the newsroom; the editorial pages are the only account of it they get, so a sentence there is a claim in exactly the way a figure in the ticker is. Nineteen such claims were swept: fourteen were enforced, three were intent written in the grammar of a mechanism and got reworded, and two were false. The worst was «لا يمكن لكاتب أن يراجع مادته بنفسه» — the policy existed, was documented, and **had no caller**, so a writer could fact-check their own article and the page said they could not. The rule that follows from it: a claim of prevention is a test you now owe. If you are not writing that test in the same pass, write "we do not" instead.
- **A policy method with no caller outside its own tests is dead code that reads as protection.** `ArticlePolicy::factCheck()`, `::publish()` and `::seo()` were all orphaned; a passing `ArticlePolicyTest` is what concealed it, because a unit test of a policy passes just as happily when nothing invokes the policy. Test the path a user takes, and sweep for `authorize('x'|can('x'|cannot('x'` before trusting any policy method that is not one of Filament's standard CRUD verbs.

### Security
- **Authorisation defaults open in Filament. Every resource has a policy or it is public to every authenticated user. Absence is not denial.** Seven resources sat in that state, the newsletter subscriber list among them: a fact-checker — view, transition, factcheck, nothing else — could read every reader's email address and use the bulk action that downloads the whole list as CSV. The docblock over `registerPolicies()` asserted the opposite, that a missing policy fell back to *permission denied*; it is the second comment on this project to assert a guarantee that was inverted in reality, and both times the comment is what stopped anyone checking. `tests/Feature/Filament/PanelAccessTest.php` holds it as an invariant — it walks every resource on disk and fails on one with no resolvable policy and no access check of its own. It is not a list of the resources we happen to know about, and it must never become one.
- **Ask the same question of anything mounted by a package or routed without middleware.** A default that opens is not visible at the call site: Horizon's packaged gate is `Gate::check(...) || app()->environment('local')`, which is open to anyone who can reach the port on a dev machine, and its job payloads carry subscriber email addresses.
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

- **A number the product cannot verify must be labelled, dated, attributed, and must disappear rather than degrade when its basis is missing. If it cannot meet all four, it does not render.** The ticker passes: every figure is editor-entered, carries `market.as_of`, names its source, and the whole set vanishes when undated. `views_count` failed it by being presented as a measurement while nothing wrote it, and `mentions_count` failed it differently by counting drafts as coverage. Any future figure is held to the same four.
- **`market.as_of` gates the whole set.** Undated figures do not render at all — a number
  with no "as of" is one a reader will assume is live.
- **The empty state is the honest state**, not an edge case. Every accessor can return
  nothing and every caller renders a labelled placeholder instead.
- **No figure is ever hardcoded in a Blade file.** Enforced by test: the suite greps the
  views for thousands-separated numbers and signed percentages sitting in markup.
- The chart is drawn **without a value axis**, because the points are an editor-entered
  shape and an axis would present them as measured.
- **A chart must not exaggerate.** Scale a series against its own level, not the
  height of its box. A stable instrument has to look stable: normalising a pegged
  rate to the full box draws the steadiest thing on the page as the most volatile,
  which is a false picture in the same class as an invented figure, expressed as
  geometry instead of a number. `App\Support\Sparkline` damps amplitude by
  range-over-level.
- **Smoothing may pass through the data, never move it.** The rendered line is the
  series that was entered. A curve that approximates its points is drawing a
  different series from the one an editor typed.
- **A direction glyph accompanies every colour.** Red/green alone fails WCAG 1.4.1 and is
  the pairing most readers cannot distinguish.

### Imagery and video
- **A photograph the site can use is a Media Library record, not a file on disk.** The publish gate reads `articles.hero_media_id`; a JPEG in `public/` is invisible to it.
- **Alt text describes the picture, in Arabic.** Never the filename, never "صورة". It is a gate rule and an accessibility requirement, and a filename fails both.
- **Disclosure is not conditional. A component that marks sponsored content takes no flag from its caller and appears on every surface the content appears on. If a new surface is added without it, the build fails.** The label lived on the article page and on none of the six card weights, so a sponsored piece sat in a homepage grid with nothing to distinguish it — and the first fix put it inside `@if ($size !== 'sm')` on the overlay card, hiding it on exactly the variant that appears in grids, which is where most readers meet sponsored content. `x-article.sponsored-flag` now takes no condition, and a file-level test requires every `card-*.blade.php` to carry it: it does not fix six cards, it prevents the seventh.
- **Credit every photograph**, using the photographer's published handle rather than an invented Arabic spelling of a real person's name.
- **No hero repeats inside one homepage section.** An image may appear many times across the site; twice in one grid reads as broken even when nothing is.
- **Forced `direction: ltr` is for values that are only ever Latin** — dates, tickers, emails, URLs. An editor-entered figure can be `+24%` or `31 مليار ريال`; wrap those in `<bdi>`, which isolates without imposing a direction. `ltr-isolate` on the second kind reverses it.
- **A section's shape follows its supply. Three features get a grid, two get a pair, one gets a feature. Never render a hole and never pad content to fill a layout.** Measured when this was written: three of six category pages were rendering a three-across grid with fewer than three featured articles in it — saudi with one, business and global with two. `global` is the case that makes it clear: it carries seven articles because global coverage only runs when it touches the Saudi market, so it is *supposed* to be thin, and the answer is a layout that reads deliberately at seven rather than seven more articles. The same rule retires a section outright when supply is zero — see the podcast in `DEFERRED.md`.
- **Seven card weights, not one card at seven sizes.** `card-article` · `card-story` · `card-lead` · `card-row` · `card-rank` · `card-overlay` · plus the big story. The variation between them is most of what makes a page look edited rather than generated; collapsing any two is what makes it read flat. Enforced by test.
- **Aspect ratio is a property of the box, not of the photograph.** The six crops live in `App\Support\AspectRatio` and in one CSS block: hero/featured 16:9 · article inline 16:8 · card thumbs 16:10 · portrait 4:5 · square 1:1 · magazine cover 3:4.1. A template names the role; no template names a number.
- **Exactly one image per page is eager; every other image is lazy.** The one is whatever the browser will pick as LCP — the big story on the homepage, the article hero, the first card of a listing's featured grid, the markets lead. A lazy LCP element costs a whole layout pass before the browser even requests the file: modelled on the category page at **2,521ms of load delay against 114ms to actually fetch it**. Making it eager was correct and cut the modelled load delay to 1,142ms — but moved LCP by about 1%, so it was *not* why the listing pages trail the homepage; see the LCP row in §9. Making *every* card eager is the opposite mistake — a dozen images queued in front of the one that decides the metric. `responsive-image` takes `:eager`; the card weights pass it through.
- **A srcset offers only candidates the box can use.** A 74px rail thumbnail listing an 1800px file will be served it on a dense screen, and the markup is paid on every card whether or not it is chosen. `MediaConversions::srcsetWidths()` narrows by scale: `thumb` (200/400), `card` (400/900), `full`.
- **Every conversion ships WebP and JPEG.** A `<picture>` with one format is not a fallback. Social cards use the JPEG: several scrapers reject WebP and fall back to no image rather than to the original.
- **Self-hosted video:** `<video>` with `preload="none"`, `playsinline`, a poster that is a real media conversion, and explicit dimensions. Autoplay is always muted. A clip over `masar.media.max_video_kb` is reported, not shipped.
- **YouTube is a facade.** Poster plus click-to-load, `youtube-nocookie`, and **no third-party request of any kind before a click** — the poster is ours, not `i.ytimg.com`.
- **AI-generated imagery does not ship as reportage.** A rendered market figure, a real company's logo on a facility that does not exist, or a national flag redrawn by a model are all fabrications, whatever the prompt said.
- **One exception, and it is enforced by a column.** A generated image may be the decorative backdrop behind the big story (the layout may hold several; the lead slide shows one per page load), where it carries mood rather than documenting a place. It is flagged `media.is_illustrative`, owned by the homepage layout and never by an article, credited «صورة تعبيرية» so the admin shows what it is, and **its alt text names no city** — the moment a generated skyline is captioned as a real place it becomes a claim the pixels do not support. A model guard rejects illustrative media on any article collection, so the rule survives whoever joins next.

### Editions and translation (owner's ruling, 2026-10-28)
- **Six editions, one registry.** `config('masar.locales')`, read only through `App\Support\Locales`. The key is the URL segment and the stored `locale` (`ur-pk`, `zh-hans`); `tag` is BCP 47 for `<html lang>` and hreflang (`ur-PK`, `zh-Hans`); `og` is Open Graph's form. Which editions are live is `MASAR_LOCALES_ENABLED` (comma list, default all six; Arabic always on).
- **Interface text lives in `lang/{code}/*.php`, never in a public template.** Arabic is the source. `TranslationParityTest` requires every Arabic key in all six editions with the same placeholders, and each edition in its own script (Urdu must carry Urdu-only letters, so Arabic pasted into `ur-pk` fails). `NoHardcodedInterfaceTextTest` walks every public template for Arabic outside comments and the «مسار» lockup, and resolves every literal key a template asks for. A sentence with a link or an email takes it as a placeholder, built and escaped in the view, so each language places it where its grammar wants.
- **Interface review is recorded, never assumed (R11).** `translation_reviews` holds one row per locale and file: content hash, string counts, status (`source` for Arabic, otherwise `pending_review` until approved), reviewer and time. `masar:translations:sync` keeps it current and is the only bulk writer; it never approves. `masar:translations:approve {locale} {file} --reviewer=<email>` is the only path to `approved`: a real account with `translation.review`, against the hash on disk. A file that changes after approval falls back to `pending_review` with `invalidated_at`. Generating lang files rewrites them, so it invalidates approvals by design.
- **Alternates are only what exists.** `App\Support\Alternates` answers hreflang, the switcher and the sitemap: a story's published translations in live editions (never a draft), and for structural pages the same route in each live edition. A missing edition is absent — it is never pointed at that edition's front page. **Fallback policy:** the switcher sends a reader to that edition's front page and says so in that language; no URL in one edition serves another edition's article text. A structured name with no row in the page's edition falls back to Arabic and is wrapped in `<bdi lang dir>` (`displayName()`, `<x-ui.bidi>`).
- **A machine-assisted translation is published as `owner_authorized`, never as `reviewed`** (owner's ruling, 2026-10-29, which superseded "drafts, never publications"). A translation is its own article row in the source's group (`source_article_id`, `translation_status`, `source_hash`); `(translation_group_id, locale)` is unique. `CreateTranslationDraft` → `TranslateArticle` (queued by id) → a person edits → `MarkTranslationReviewed` (not the translator) → the ordinary workflow and gate, which admits a translation only when `TranslationStatus::isPublishable()`: `reviewed`, or `owner_authorized` while `masar.translation.owner_authorized_publication` (`MASAR_TRANSLATION_OWNER_AUTHORIZED`, default true) is on. Switch it off and the gate is back to reviewed-only. A reviewed translation is never overwritten by a machine run; "outdated" is computed from the hash, never stored, and shown on the article (a list may not load blocks to compute it).
- **Database content in every edition.** `ContentTranslationsSeeder` imports `database/seeders/data/content/{locale}.json` and `{locale}-*.json`, keyed by sha1 of the trimmed Arabic text: articles (through `CreateTranslationDraft::draft()`, then published as `owner_authorized`, `translation_origin = machine-assisted`, fact-check copied from the source), entity and taxonomy rows, opportunities, image alt and credit (`alt_translations`/`credit_translations` on the media record, read by `App\Support\MediaText`), bylines (`users.name_translations`, read by `User::display_name`), and market labels (`market.translations`, read by `App\Support\MarketText`). It is additive: a story with one missing segment is not published at all, and an existing translation is never replaced, except a field still byte-identical to the Arabic source, which is by definition untranslated. `masar:translations:coverage` reports stories, entities, opportunities and alt text per edition and exits non-zero on a gap.
- **`replicate()` copies loaded relations.** A draft made from a source with `sources` loaded wrote the translated citations onto the *source's* rows. `CreateTranslationDraft` clears relations straight after `replicate()`; a test asserts the original is untouched.
- **A column-limited user load drops the byline's translations.** `author:id,name` left `name_translations` unselected, and the fallback printed the Arabic name on every card of every other edition with no error. `display_name` now throws `MissingAttributeException` when the column was not selected, so the mistake is a 500 in the crawl rather than a silent leak.
- **Leak check:** in a non-Arabic edition, Arabic-script text outside an element whose `lang` declares it is a leak, and so is any in `<head>` or JSON-LD apart from the «مسار» lockup. `ContentTranslationsTest` holds it for one story; the full crawl lives in the localization audit in `REPORT.md`.
- **No outside translation service (owner's ruling).** `TranslationProvider` ships with `NullTranslationProvider` only; a declined draft is `awaiting_translator`. `masar.translation.allow_external` is a second switch: no article text leaves the server until it is true, whatever driver is bound. A provider never sees URLs, figures or Latin names — `SegmentGuard` swaps them for markers and rejects a result that loses one or is not in the target script. The editorial-standards page promises human-written originals and says the other editions are machine-assisted and not yet reviewed by native speakers; a test fails the day a real driver is bound, so the page is revisited first.
- **Typography per script** is `html[data-script]` in `app.css`: brand faces for Arabic and Latin, system faces for Nastaliq, Devanagari and Han (nothing fetched from outside), Nastaliq at 2.4 leading. A label that is long in French is the stress case: section titles wrap rather than `whitespace-nowrap`, and navigation uses short menu labels distinct from full section names.
- **The whole platform is six-language, the admin panel included** (owner's ruling, 2026-10-29, revoking "admin labels stay Arabic"). Admin text is keyed by its Arabic source in `lang/admin/{code}.json` (an extra JSON path in `AppServiceProvider`), so the code still reads in Arabic: wrap a new literal in `__()` and add the key to all six catalogues. `AdminLocalizationTest` fails on an unwrapped Arabic literal in `app/`, a key one edition lacks, or a placeholder that differs; its two lists name every file whose Arabic is data (transliteration and folding tables, stored audit text, console output) or a constant translated at its use site. PHP cannot call `__()` in a property default or a constant: Filament labels are getter methods, and anything the panel builds at boot (navigation groups, brand, switcher label) is a closure, or it is fixed in whatever locale the app booted with.
- **Admin locale:** `SetAdminLocale` runs before auth and is **persistent** — Livewire's update requests re-run only persistent middleware, so without it every validation error and action notification came back in Arabic. Order: `users.locale` → `?lang=` on the sign-in page (session) → the public `masar_edition` cookie → Arabic. Each person's choice is their own, from the user menu. `User::preferredLocale()` makes notifications and mail render in the recipient's language, whatever locale the worker booted with. Direction comes from Filament's own translation. `EditionTranslationLoader` maps `ur-pk`→`ur` and `zh-hans`→`zh_CN` for package namespaces only.
- **Laravel and Filament messages:** `lang/{code}/{auth,pagination,passwords,validation}.php` in all six (Laravel ships English only, so every other edition rendered `validation.required` until 2026-10-29), and `lang/vendor/{namespace}/{code}/…` supplies only the Filament keys the package lacks — filed under the package's code (`ur`, `zh_CN`), because the loader falls back to it only when our code finds nothing. 1,058 keys resolve in every edition with fallback off, asserted at runtime. `validation.attributes` stays an empty array in every edition.
- **FilePond (inside Filament's file upload) has no Hindi or Urdu and keys Chinese as `zh_CN`,** so those panels showed English "Remove"/"Cancel". FilePond reads options from `data-*` on its input; `FileUpload::configureUsing()` in `AppServiceProvider` supplies `admin.filepond.*` that way. Do not edit the published bundle.
- **Admin type per script:** `html:lang(ur|hi|zh)` in the admin theme sets Nastaliq / Devanagari / Han system faces on the variables Filament reads (`--font-family`, `--font-sans`, `--font-display`); setting them on elements alone missed the sidebar. Nastaliq's descenders are cropped by `truncate` at Latin leading — a final ی loses its tail — so clipped Urdu labels get a 2.2 line box.
- **Filament closure names, again:** `getOptionLabelFromRecordUsing(fn ($r) …)` and `formatStateUsing(fn (SomeEnum $s) …)` took `/admin/companies`, `/admin/opportunities` and `/admin/people` down with 500 on real data while every smoke test passed (no related rows, so the closure never ran). The guard in `ResourceSmokeTest` now covers every Filament-evaluated method and allows only names Filament injects.
- **Laravel's own notifications read their text by the English sentence, from `lang/{code}.json`.** Filament's password-reset mail went out in English in every edition until those sentences were added; `AdminLocalizationTest` sends it per edition. Any new framework notification needs its sentences in all six JSON files.
- **Correcting imported machine text:** `{locale}-corrections.json` (sha1 of the old machine text → new) is applied by `ContentTranslationsSeeder` to `machine-assisted` rows only, and only to a segment still exactly the old text. Chinese amounts are grouped in 亿/万 (400亿, not 40 十亿).
- **Backups are disk-safe:** `deploy/backup.sh` checks free space before writing, writes `.partial` files, reuses an unchanged media archive by fingerprint, and prunes complete sets only (see RUNBOOK). On dev, a pre-change snapshot is a database-only dump.
- **Stored text stays as written; its presentation follows the reader.** Audit entries keep their original description; `AuditDescription` presents a status transition from its stored enum values and a known sentence through the catalogue, and shows anything else as stored.

### RTL and Arabic
- Use Tailwind **logical properties** (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) from the first line. Never `ml-`/`mr-`/`pl-`/`pr-`.
- Force Western numerals: `font-variant-numeric: lining-nums`.
- Wrap inline Latin text (company names, tickers) with `dir="ltr"` + `unicode-bidi: isolate`.
- Arabic body text needs larger size and looser leading than Latin: 18px / 1.95.
- **`font-display` is a decision per face, not a global default.** The body face is `optional` — a reader who misses the 100ms window reads the fallback for the whole visit and never watches a paragraph reflow, which is the failure a long Arabic article makes most visible. The display face keeps `swap`: a headline arriving late is visible, and one never arriving is worse. Both faces are preloaded — an `optional` face that misses its 100ms window is dropped for the whole visit, and the preload is what gets it inside that window (measured: removing it cost ~200ms of FCP and bought nothing back).

### Accessibility
Semantic HTML, visible focus states, alt text required on every image,
keyboard navigation, `prefers-reduced-motion` respected.

- **An automated checker is a floor, not a pass.** axe reports zero violations
  across nineteen pages at two viewports — and found none of what the Day 6 pass
  actually fixed: three text inputs with `focus:outline-none` behind a 1px
  border colour change (every input a reader ever touches, including site
  search), and thirteen targets under 24×24 on the homepage alone, the search
  icon at exactly 16×16 and the hamburger — the only route to the rest of the
  navigation — two pixels short at 22px. Walk the tab order and measure the
  boxes; the checker will not.
- **A target grows without the design moving.** `inline-flex min-h-6
  items-center`, not extra padding: the box reaches 24px (WCAG 2.2, 2.5.8) while
  the text stays on its existing centre line. Almost every row on this site is
  already taller than 24px, so this costs nothing. `focus:outline-none` is
  forbidden outright and a test enforces it — the site-wide
  `:focus-visible { outline: 2px }` floor in `app.css` is only a floor if no
  template opts out.
- **Silent video is not captioned video, and neither is assumed.** A clip with
  an audio track is synchronised media and owes captions (1.2.2); a silent one is
  video-only prerecorded media and owes a text alternative (1.2.1). The player
  cannot tell which it holds, so `has_audio` is measured once with ffprobe at
  seed time and stored beside `alt`. TRUE owes captions, FALSE renders «مقطع بلا
  صوت» — a reader who cannot hear must know the silence is the clip and not a
  fault at their end — and NULL says nothing either way, because a page may not
  describe what nobody established. Video alt text follows the same rule as
  images: never the filename.

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
- **A rule with more than one implementation has no implementation. When two places must apply the same rule, they share the code — not the intent.** "Counters count published rows only" existed in four places: one action had it right, a sibling action had no filter, and `masar:recount` held two more copies — one of which was the drift detector, so the measuring instrument agreed with the bug. They now share `App\Support\PublishedContent`, and the command calls the actions instead of restating their SQL.
- **Every cache entry carries a tag. An untagged entry cannot be flushed by any mechanism we have and will eventually hold something stale nobody can find.** `intelligence:company-names` sat untagged for months: no `Cache::tags(...)->flush()` reached it, so a renamed company would not have affected importance scoring for an hour and nothing could have hurried it. That invisibility is the property, not the hour. The full register of what can go stale is `tasks/STALE-SNAPSHOT-INVENTORY.md`.
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


**After an edit that should change rendered output, verify the output changed.**
An edit tool that anchors on exact text fails by matching nothing, and a silent
no-match produces the same console output as a successful edit. A hero's
`object-position` was reported as shifted for a whole pass while it sat at the
default, because the anchor had 24 spaces of indentation and the call site had
20. Read the computed value back, not your own diff.


**A verification tool must not reimplement the logic it verifies. If the
checker holds its own copy of the rule, it will agree with the bug. Call the
implementation.** `masar:recount` measured counter drift with its own SQL, and
that SQL was missing the same published filter the counter was missing — so it
reported **zero rows out of date on data where every row was wrong**, and
rebuilding restored the inflated values over a correct fix. A drift detector
that shares the defect it detects is worse than no detector, because it carries
a green tick.

**Assert the property, not a snapshot of it. A threshold gets raised until it
means nothing; an invariant — same work over three times the data costs the same
queries — cannot be quietly relaxed.** The first version of the co-mention guard
asserted "at most 3 queries" and failed at 4, which was the true figure and
still bounded; raising it to 4 would have taught nothing and the next person
would have raised it again. Several guards in this codebase are thresholds
wearing an invariant's clothes — when you touch one, convert it.

**An automated checker reports what it can reach. Zero violations means the tool
found nothing, not that there is nothing. Walk the page yourself.** axe returned
zero across nineteen pages at two viewports and found none of what the
accessibility pass actually fixed: three text inputs whose focus ring had been
replaced by a 1px border-colour change — which reads as *styled* rather than
absent — and fifty-three targets under 24×24, the site search icon at exactly
16×16 and the hamburger, the only route to the rest of the navigation, two
pixels short. Walking the tab order and measuring every box found all of it in
one run. The same applies to a defect already written down as accepted: the
`DEFERRED.md` note on target size was an estimate, and the estimate understated
it by an order of magnitude. **An accepted defect recorded from an estimate is
an unmeasured defect with permission.**

**A protection provided by a package is unverified until you have watched it
refuse. Read the source to know what to test, not instead of testing.** We have
now been wrong twice about what a package default does, in opposite directions.
Filament's authorisation *fallback*: a resource with no policy reads as locked
and is open, and a docblock in this repo asserted the opposite for months.
Filament's *login rate limit*: real, five attempts, but discovered by reading
`vendor/` — which is the same act of trust that produced the first error.
`LoginThrottleTest` now watches a sixth attempt be refused, and watches correct
credentials fail to get through the limiter, so an upgrade that drops it fails
here rather than in production.

**A policy method with no caller outside its own tests is not a rule, it is a
comment that passes CI. Sweep for orphans when you add one.** Three for three on
this project — `ArticlePolicy::factCheck()`, `::publish()` and `::seo()` — which
is not a coincidence but a property of how policies get written: the method
first, the wiring later, and nothing complains about the gap. `seo` was the
worst, because the SEO tab carries `noindex`: any writer could remove an article
from search and the change leaves no visible trace on the page, so it surfaces
months later as "why does this story get no traffic" and is never traced back.
The sweep is `authorize('x'|can('x'|cannot('x'` across `app/ resources/ routes/`
excluding `app/Policies/`; Filament's standard CRUD verbs are called implicitly
and are not orphans. Test the path a user takes — a policy unit test passes
whether or not anything invokes the policy.

**A blanket edit across test files will eventually change a test's premise
rather than its fixture. After a broad replacement, read what changed, not just
what passed.** Two instances in one day: a disclosure component dropped inside
an `@if ($size !== 'sm')` so it vanished on the variant that appears in grids,
and a sweeping fixture change that made an article published in the one test
whose whole premise was that it was a draft. Both suites stayed green.

**A health check must observe the artefact, not its inputs. Querying the
database to assert the page is correct proves only that the data exists. Read
the rendered output.** The decay check reported `navigation renders — 23 menu
items` while the site served a masthead with no navigation at all: the rows were
there, an empty cached snapshot sat in front of them, and the check was counting
rows. Every health check in this project is to be read against that standard.

**Every task ends with a five-minute decay check, reported in `REPORT.md`.**
Not an audit — a pulse. Detail work crowds out the question of whether anything
behind it has quietly stopped working: a newsletter form sat on every page of
the public site as `method="GET"` with no action for six sessions, while the
attention went to a scrim and a sparkline. Nobody was wrong to care about the
scrim; the mistake was never asking what had decayed while we did.

Run `php artisan test`, then `php artisan masar:decay`, and paste both into
`REPORT.md`. They are two commands on purpose — see the pipe note in §9 for why
the suite is not launched from inside another process. The decay check covers:

1. **Suite green** — recorded from your own `php artisan test` run.
2. **Queue drained** — depth, failed count, and whether a worker is actually running.
3. **`migrate:fresh --seed` clean** — against a scratch database, never the dev one. Opt-in with `--with-seed`: the seed transcodes video and normalises every demo photograph and takes ~15 minutes, so it belongs before a release or after a migration, not in every pulse.
4. **The seven end-to-end flows** still pass (the list in `tasks/STATUS.md` §2).
5. **Every public form still posts where it claims to** — method, action, and a
   route that exists. This is the specific check that would have caught the one
   that started it.

A red line is not a reason to stop; it is a reason to say so in the report
rather than discover it six sessions later.

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
| **LCP — every figure is a Lighthouse simulation on dev, not a production measurement** | **Method.** Lighthouse 12.8.2 mobile default, `throttlingMethod: simulate`: the page is loaded *unthrottled* on `php artisan serve` behind a local gzip proxy (observed LCP 150–220ms), and LCP is then *computed* from the request graph under 150ms RTT · 1.6Mbps · 4× CPU. The model's input is the page's bytes and dependencies, so the same page gives the same number: three runs of `/ar/saudi` read **3686 / 3686 / 3686** because the graph did not change, not because three measurements agreed. That is reproducible by construction, not a measurement, and **no production figure exists** — there is no server. Phase breakdowns below are the model's (the `largest-contentful-paint-element` audit); the trace's own phases come from the unthrottled load and must never be quoted beside a simulated LCP — an earlier version of this row did, quoting a 4–14ms load delay that was not the model's. **Category page (`/ar/saudi`), before and after the eager-image fix.** Before: LCP 3725ms (2026-09-14 audit, slug not recorded); modelled load delay 2,521ms against a 114ms fetch. After (2026-10-27): LCP **3686ms**, modelled TTFB 455 · load delay 1,142 · load time 116 · **render delay 1,973ms (54%)**. The fix cut modelled load delay by 1,379ms and moved LCP by 39ms — **about 1%**. The time it saved moved into render delay: the image is loaded and waiting to paint. **LCP on this page is dominated by something not yet identified.** The fix was right (§5) and is not the lever; do not present it as an LCP win. `/ar/business` reads 4059ms on the same harness. **Homepage.** 3685ms (original, likely taken with compression off — not trusted) · 2053ms / CLS 0.061 (2026-09-14) · 3086 / 3089ms (2026-10-27). The last two differ by a second and nothing recorded explains it; neither is the homepage's number. Its 3685ms-era breakdown was also render-delay-dominated (57%). **Product rule that stands regardless:** nineteen sections with real photography is the product — do not cut sections to move this number. CLS 0.041 on home was accepted as the price of `font-display: optional` on the body face. |
| Media storage | `MEDIA_DISK` selects it. Local is `media` (`storage/app/public/media`, served through `storage:link`); production is `r2` with the `R2_*` credentials from the environment. `config/media-library.php` reads that one key, so nothing else in the app names a disk. |
| Demo imagery | `ImageLibrarySeeder` turns `public/images/` into real media records. It normalises each source to a 2400px master once and generates each conversion once per **image**, not once per article — the same 28 photographs behind 130 articles is 704 identical conversions otherwise, which measured at ten minutes of `migrate:fresh --seed` against 4 seconds now. `MediaConversions::withoutGenerating()` is what makes that safe; a caller that uses it and forgets to write the files leaves records whose conversions 404. |
| **No media master in the repository** | GitHub rejects any file over 100 MB, and two 4K stock clips — 167 MB and 101 MB — did exactly that. Deleting them from the working tree does not fix it: the blob is already in the pack and the push fails on the same object. `public/images/` holds **web-delivery renditions only**, named `<id>-web-<width>x<height>.mp4` so the name states what the file is, 1080p on the long edge, H.264, and **under `masar.media.max_video_kb`** (10 MB). Masters live in `storage/app/video-masters/`, which is untracked. `.gitignore` cannot express a size rule, so the size rule is a test: `tests/Feature/RepositoryWeightTest.php` fails on any tracked file over the budget and on any tracked name with a master's shape. A clip that is still over budget after transcoding is not committed at all — it is re-sourced. |
| Demo video | `VideoLibrarySeeder` transcodes each source to 1080p H.264 once into `storage/app/seed-video` and caches it there, so the first run on a fresh checkout takes minutes and every later run takes seconds. It needs `ffmpeg` on `PATH` and skips with a warning without it. |
| Looking at the page | **After any visual change, open the rendered page and look at it.** A suite cannot tell you every photograph on the site is missing, that a section is blank, or that an Arabic figure is rendering backwards — all three shipped past green tests. Screenshot the app, not the prototype. `content-visibility: auto` means a full-page capture of off-screen sections comes back blank, so scroll each slice into the viewport and capture that. |
| **`php artisan test` must not be piped** | Piping it (`php artisan test \| tail`) hangs indefinitely *after the suite has already passed*. Measured: Pest reports `679 passed · Duration 50.22s`, then the process sits at 0% CPU — one run was left for two hours and one minute. Something spawned during the run inherits stdout and outlives Pest, so the reader never sees EOF. Redirect to a file instead — `php artisan test > /tmp/suite.txt 2>&1` exits in 0s with the same result. This is also why nothing shells out to the suite: Laravel's `Process::run()` captures output through the same pipe and hangs identically, which cost hours of chasing an imaginary Pest deadlock. If the suite looks hung, check Pest's own reported duration before believing it. |
| **A restore from a dump must be followed by `cache:clear`** | `DatabaseSeeder` ends with `Cache::flush()`, so `migrate:fresh --seed` leaves a consistent site. A backup restore does not: `mysql < dump.sql` never runs application code, so nothing flushes the caches the previous contents left behind. Reproduced deliberately — wipe, load the homepage once so an empty snapshot is cached, restore the dump, and the site serves a populated database through an empty cache with no error anywhere. `masar:decay` catches it and now prints the remedy; nothing else will. **Restore, then `php artisan cache:clear`, then run the decay check.** |
| **Tests cannot see cache-state bugs** | `phpunit.xml` sets `CACHE_STORE=array`, so every test runs against a cache that is empty at the start and discarded at the end. A stale or empty cached snapshot in front of a populated database — the failure that removed the main navigation and the market ticker from the live site for hours — is **invisible to the suite by construction**. This is not a coverage gap to close by writing more tests; it is a boundary of the method. `masar:decay`, which reads the rendered page against the real cache, is the only thing standing between us and that class of failure. Do not delete it, and do not weaken it into a database query. |
| Test-suite concurrency | Two runs against the single `masar_test` schema truncate each other via `RefreshDatabase`, producing scattered failures that look like real bugs. `Tests\TestCase` takes an exclusive `flock` on `masar-test-suite.lock` and refuses the second run with an explanatory message. The lock is held by the OS, so a killed run never leaves a stale one. Opt out with `MASAR_ALLOW_CONCURRENT_TESTS=1`; Pest `--parallel` is detected and exempt because it gives each process its own schema. |

**Never** change these without saying so. A silent environment change is the hardest
class of bug to find later.
