# TASK 07 — Masar Intelligence, Parts A–C and F

Sources, ingestion, classification and retention are built and tested. The
inbox (D) and rules engine (E) are not started.

---

## The number you asked for: 81.8% rules classification

Measured against the seeded corpus, not estimated.

| path | items | share |
|---|---|---|
| rules | 18 | **81.8%** |
| unclassified | 4 | 18.2% |
| ai | 0 | 0% |

**That figure is softer than it looks and I want to be explicit about why: I
wrote both the corpus and the keyword sets.** Any rate measured that way is
partly a measure of my own consistency. The four it missed are the ones I
seeded deliberately to be vague:

- الرياض تستضيف فعالية اقتصادية إقليمية
- حديث عن مرحلة جديدة في مسار القطاع
- ملامح المشهد الاقتصادي بعد التغيرات الأخيرة
- أصداء واسعة لما جرى هذا الأسبوع

Those are the shape of what AI would be paid to do: soft, headline-only,
no document-type marker. **The real rate needs a week of actual feed volume**,
and the `classification_path` column now exists to produce it. Treat 81.8% as
an upper bound on a friendly corpus, not as a forecast.

Importance across the corpus: min 28, median 57, max 74.

## Parts A, B, C

**Schema.** Three tables as drafted and approved: `sources`, `source_items`
(raw, internal), `intelligence_items` (processed). The split makes retention a
one-line update rather than a cascade.

**Fetchers.** `RssFetcher` (RSS 2.0 and Atom in one class), `ApiFetcher` driven
by each source's `parser_config`, `SitemapFetcher` with a Google News title and
a slug fallback. All polite behaviour lives in one `HttpClient`: the named
User-Agent with a contact URL, conditional GET, and 429/Retry-After honoured as
an instruction.

**Dedup.** Four stages, cheapest first. Threshold 16, calibrated and pinned by
a test that asserts the whole distribution — identical at 0, near-duplicates
1–16, different stories 17+.

**Classification.** Keywords → source category → AI → unclassified. The keyword
sets are config, so an editor can add the phrase a ministry actually uses
without a deploy. `NullAiProvider` ships and declines everything; a test binds a
stub provider to prove the seam works before any provider exists.

**Importance.** Source trust × document type × entity match × recency, with
every input returned alongside the score and a human-readable `because` for
each. A number an editor cannot interrogate is one they learn to ignore.

**Health and auto-disable.** `RecordSourceResult` is the only writer of a
source's health. Auto-disable at the configured threshold, notification to
super-admins and editors-in-chief, once and not on every subsequent failure. A
304 is a success. Exponential backoff, capped, and a publisher's Retry-After
always beats our arithmetic.

**Retention.** `masar:purge-intelligence` nulls ingested body text at 30 days
and deletes dismissed items at 30 days. The processed record survives — it is
our own writing and our evidence that we saw the story.

## Two bugs worth naming

**`?? null` on an array offset of null still dereferences it.** `$duplicate['item']->getKey() ?? null`
threw whenever there was no duplicate — which is the common case. Caught by the
containment tests, not by the dedup tests, because the dedup tests always had a
duplicate to find.

**The admin's active sidebar item was invisible, and had been all along.** The
theme override targeted `.fi-sidebar-item-active .fi-sidebar-item-button`;
Filament renders `.fi-active` and `.fi-sidebar-item-btn`. **That rule had never
matched anything.** Filament's default light pill was showing underneath, and
every sidebar label is cream — so the label telling you which page you are on
measured, by computed style, `rgb(246,244,239)` on `rgb(246,244,239)`. About
1.05:1.

This is the fifth silent failure and the first one I found by looking rather
than by testing: every label was present in the DOM and correct in the markup.
I only caught it because my own new nav item appeared blank and I checked
whether it was my resource or the theme — it was the theme, on every page.
Fixed, and a test now asserts the selectors exist in what Filament renders.
CLAUDE.md §6 has the convention.

## The command

```
php artisan masar:check-sources --once
```

| Source | Health | New | Failures |
|---|---|---|---|
| وزارة الصناعة والثروة المعدنية | failing | 0 | 1 |
| هيئة السوق المالية | failing | 0 | 1 |
| … four more `.test` domains | failing | 0 | 1 |
| **تغذية محلية للعرض** | **healthy** | **2** | **0** |

The demo publishers are fictional `.test` domains that resolve nowhere, so they
exercise the failure path and show nothing of the rest. The seeder now also
copies one fixture into `storage/app/public` and points a source at it, so a
real HTTP round trip through fetch → parse → dedup → classify → store is
demonstrable. Second poll of the same feed: **0 new**, health still healthy —
stage one of dedup doing its job.

Three items in that feed, two ingested: the third has no title, and an entry an
editor cannot act on is dropped rather than added to the inbox empty on every
poll.

## What is asserted, and what I only clicked

**Asserted (612 tests, 1,998 assertions):**

- Each fetcher against recorded fixtures, including conditional headers actually
  being sent, 304 as success, Retry-After honoured, malformed XML as a source
  failure
- Each dedup stage independently; a near-duplicate flagged and **not** discarded;
  two different stories from one publisher not matched; the SimHash distribution
- Classification: rules path, source-category fallback, AI path with a stub, and
  that the path is recorded
- Auto-disable at the threshold with notification, exactly once; streak reset;
  backoff; `due` scoping
- Body text stored only where `legal_mode` permits; hidden from array and JSON
  casts; no public controller or view references the models; not reachable via
  search or the homepage; both retention windows
- Source resource: health column, navigation badge, permission gate, reactivate
  clearing the streak

**Clicked, not covered:** I logged into the admin and looked at
`/admin/sources` — the health badges, the streak against its threshold, the
seven-day counts, the sort order, and the reactivate action appearing only on a
disabled source. That is how I found the theme bug. The rendering itself is
asserted through Livewire; the visual result is not, and I am not adding a
browser-test dependency for it.

## Not built

Parts D (inbox) and E (rules and alerts). When I reach D's keyboard workflow I
will report the same way: the underlying actions and authorisation asserted,
`j/k/a/r/Enter` clicked and named as uncovered.

## State

- 612 tests pass (1,998 assertions). Pint clean.
- axe: 0 violations on the public site; the admin theme fix verified by computed
  style.
- `migrate:fresh --seed` reproduces everything: 6 sources, 22 items, 1 duplicate
  flagged.
- CLAUDE.md §6 gained the dead-CSS convention; §7 the silent-failure rule.
