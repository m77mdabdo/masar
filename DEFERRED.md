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
`masar:reclassify` re-derives it. **Done 2026-09-15** in the Day 5 slot:
`liveImportanceScore()` computes recency at read time, `scopeOrderByLiveImportance()`
sorts on it in SQL, and the stored components stay for auditability. Left here
because the stored column is still what `masar:reclassify` writes and a reader of
that column alone would still be reading a frozen number.

## Ads — mechanism built, not reachable

**Needs a creative editor and at least one placement before it does anything.**

Corrected 2026-09-16; this entry said "the mechanism shipped" the day before and
that was wrong. The orphan sweep found `x-ads.slot` referenced by no template at
all, and underneath it: **zero placement rows**, with no seeder and no surface
that creates one, and **no way to create a creative** — no relation manager on
the campaign, no placement select, no image upload. The chain breaks in three
places at once, so nothing reaches `AdServer` from a browser.

What exists and is correct: the three models, `AdServer` (placement lookup,
Redis-buffered impressions, and a failure path that never reaches the reader),
the click controller, `masar:flush-ad-impressions` and its schedule entry, the
campaign CRUD, and the slot component itself — which renders nothing at all when
nothing is running, as it should.

What it needs: a creative editor under the campaign (placement, image, alt,
destination), at least one placement, and slot calls on the templates meant to
carry one. The scope ruling still holds — no targeting, no bidding, no reporting
beyond impressions and clicks.

**The split worth naming.** The revenue feature is not built. The *editorial
commitment* around it is, and is tested: a sponsored article cannot publish
without a named sponsor; the «محتوى مدعوم» label is a component that takes no
flag from its caller and appears on every one of the six card weights, enforced
by a file-level test; the label cannot be removed after publication
(`DisclosureCannotBeWithdrawn`, five tests); and every ad link carries
`rel="nofollow sponsored noopener"`. Everything we promised a reader about paid
placement is real. What is missing is the part that would make us money.

## WCAG 2.2 target size — resolved 2026-09-16

**Was:** metadata links were given a 24px minimum height with vertical padding
only, so the line did not visibly change, and it was accepted rather than fixed.
That note understated it: the Day 6 pass measured every tab stop on three pages
and found **thirteen** targets under 24×24 on the desktop homepage alone,
including the site search icon at exactly 16×16 and the hamburger — the only
route to the rest of the navigation — at 22px wide, two pixels short.

**Now zero**, on the homepage at both viewports and on the article page. The
pattern throughout was `inline-flex min-h-6 items-center` rather than more
padding: the target grows to 24px while the text stays on its existing centre
line, so nothing in the design moves. The rows these links sit in were already
48px tall, which is why it cost nothing.

One target is unchanged by decision, not oversight: nothing. Every link measured
now clears it.

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

## Corrections as a mechanism

**Written as a promise, never built.** The corrections page described appending a
correction line to the article carrying the old wording, the new wording and the
date, and lifting a material correction to the top of the piece. None of it
exists: no `corrections` table, no column on `articles`, no component, nothing
rendered. The page was rewritten on 2026-09-16 to describe what we do rather
than what the system does.

This is a real gap in the trust model, not a cosmetic one. A correction that
lives inside the body text is invisible to a reader who read the article before
it was made, invisible to a reader comparing versions, and invisible to anything
that could list what we got wrong. The partial substitute now in place is the
last-updated date in the byline, which was already stored in
`articles.updated_content_at` and surfaced only to crawlers.

**What it needs:** a `corrections` table (article, what it said, what it says
now, why, who, when, and whether it is material), a component at the top of the
article for material corrections and the foot for the rest, a Filament action on
the article that writes one instead of an editor retyping the body, and an index
page listing every correction the publication has made. The index is the part
that makes the rest credible.

## Orphaned policy methods

`ArticlePolicy::factCheck()`, `::publish()` and `::seo()` all had zero callers
outside their own tests. All three now have real ones — the fact-check
transition, the publish button's disabled state, the SEO tab's visibility — and
`tests/Feature/Filament/PolicyReachabilityTest.php` drives the panel rather than
the policy, because a policy unit test passes whether or not anything invokes
the policy.

**Deferred:** there is no automated guard against the *next* orphan. The sweep
that found these was a grep, run by hand. A test that walks every policy class,
skips Filament's standard CRUD verbs, and fails on a custom method with no call
site in `app/`, `resources/` or `routes/` would make it permanent. Worth doing;
not worth the Day 6 slot.

## A test that keeps the orphan sweep honest

`tasks/ORPHAN-SWEEP.md` found four real orphans across eleven categories, by
greps run by hand. Exactly one of those categories now has a test behind it:
`PanelAccessTest` fails on a Filament resource with no gate.

The rest — a policy method with no caller, an action with no caller, a Blade
component nothing renders, a permission nothing checks — were found once and are
guarded by nothing. A single `tests/Feature/OrphanSweepTest.php` holding those
four greps as invariants, each with an explicit allow-list for what we have
decided to keep, would make the sweep permanent instead of remembered.

Worth a slot. Not worth Day 6.

## A media library surface

`media.manage` was a permission held by four roles and checked nowhere, because
there is no media library resource — uploads happen inside the article form and
are gated on editing the article. The permission is removed rather than left as
a promise.

When a media library does exist (browse, replace, re-credit, retire an image
without opening every article that uses it), it brings `media.manage` back with
something behind it, and social, video and designer stop being read-only panel
users.

## Footer links stored as literal URLs

The footer's «المنصة» column is a database menu whose items store `/ar/about`,
`/ar/team`, `/ar/advertise` as literal strings rather than route names. Nothing
checks them: change a route and the footer points at a 404 with no failure at
build, test or request time — and no `route()` grep will ever show those pages
as linked. Not broken today. Wants either a link checker in the decay check or a
route-name column on the menu item.
