# Deferred

Everything not built, with the reason. Kept because a thing left out on purpose
and a thing forgotten look identical six months later.

The rule this list enforces: **if a section cannot fill honestly, it does not
ship.** A heading with nothing behind it is a promise to a reader, and padding
content to suit a layout is the same failure pointed the other way.

---

## Podcast — removed from the site

**Removed 2026-09-15.** There is no podcast: no episode model, no audio, no
`podcast` case in `ContentType`, and zero rows that could fill one.

The section existed and was configured to pull `content_type = interview`, so it
presented interview articles under a heading promising audio. That is worse than
an empty section, because it looks filled.

Gone from the enum, the homepage view, the seeder and any existing layout (a
migration deletes the rows, since a surviving row would throw on the enum cast).

**To bring it back:** an episode model with real audio, a player that respects
`preload="none"` and a real poster, and at least enough episodes to fill the
section. Add the section back with the podcast, not before.

## Economy as a category — struck

Never existed in the code; a leftover in the plan from the earliest sitemap,
before the Saudi focus was locked. The six pillars are business, global,
insights, saudi, startups, stories, and the launch three are **saudi, business,
insights**. Nothing to remove — recorded so nobody re-adds it from an old
document.

## Reports

`content_type = report` exists with 8 published articles, so the section fills.
There is no separate report entity — no cover, no download, no distinct page
shape. The section renders reports as articles, which is honest but is not the
product a report should be.

**Not deferred because it cannot fill; deferred because the richer form is Day 7
demo-content work.**

## Issues

The issue page renders from featured topics rather than an issue record. It
fills and it reads correctly, but an "issue" is not a first-class thing in the
schema. Same reasoning as reports.

## English content

`en` is configured with `enabled => false` and `/en` returns 404. The locale
architecture is real and used throughout — translation tables, `translation_group_id`,
hreflang. There is no English content and no way to reach it.

**Deliberate:** an English version of an Arabic story is rarely a translation. It
is a different headline and often a different angle, which is why the schema
models it as a separate row rather than a translated column.

## Reader accounts

Not built and **not scaffolded** — there is no `reader` guard in
`config/auth.php`, contrary to what CLAUDE.md §2 claimed until an audit checked.
When they are built, the guard is the first thing to add.

## Entity-aware deduplication

The intelligence title stage uses SimHash over headlines, and it is known-weak
by measurement rather than suspicion: two different companies' results score 7
apart while a genuine rewrite of the same story scores 20. No threshold
separates the classes.

**The replacement is named:** compare entities, so two headlines naming
different companies cannot be duplicates however similar the sentence. The
extractor exists (`App\Support\EntityMatcher`). Until then the threshold sits at
10, the behaviour is flag-never-discard, and the URL stage carries the load —
it produced 18 true positives on the first feed that republished articles.

Evidence and the three labelled pairs are in `config/masar.php`.

## Saudi regulator coverage

**Measured, not assumed:** no Saudi regulator publishes a machine-readable feed.
SPA has no RSS at any path, Tadawul's own `robots.txt` returns 403, CMA
redirects, and GASTAT, the Ministry of Finance and Vision 2030 have nothing at
any conventional path or in autodiscovery.

So **regulator coverage is a manual editorial function, not an automated one** —
a person with a browser and a checklist. The intelligence pipeline covers what
publishes feeds, which today means newspapers and foreign institutions. Do not
let a later reader assume the inbox watches the regulators.

Owner is approaching SPA and Tadawul directly about access rather than scraping
around them.

## Meilisearch

`SearchArticlesQuery` folds Arabic on both sides and runs a LIKE query. The swap
point is inside that query object and no caller changes. Trigger: roughly 2,000
published articles. There are 72.

## Read-time importance scoring

`intelligence_items.importance` stores a value whose recency component decays
against `now()`, so every score freezes an item's age at ingest and the recency
weight effectively does nothing — a third of the corpus drifted in a day.
`masar:reclassify` re-derives it. **Scheduled for the Day 5 slot:** compute
recency at read time, keep the stored components for auditability.

## Ads

No ad slots, no campaigns, no scheduling. Sponsored content is partly there and
the part that matters works: `is_sponsored` is enforced by the publish gate,
which refuses to publish without a sponsor name, and the label cannot be removed
after publication.

## WCAG 2.2 target size

Metadata links were given a 24px minimum height with vertical padding only, so
the line does not visibly change. Accepted rather than fixed, recorded here so
the next accessibility pass knows it was a decision.

## Homepage LCP

Recorded in CLAUDE.md §9 as **superseded pending a clean re-measure**. The
3685 ms figure and the 2053 ms re-measure disagree, and every page moved by a
similar amount on the same machine in one session, so neither is trustworthy
until one clean run from the production server replaces the row. The reasoning
stands: render delay dominates and the remaining levers cost design.

## The 72-article floor

A full homepage needs 72 published articles; the layout's appetite is 70. Below
that, sections under-fill. Recorded because it is a constraint on demo content,
not a bug.
