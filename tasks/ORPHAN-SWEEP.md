# Orphan sweep

Swept 2026-09-16, after `ArticlePolicy::factCheck()` turned out to have no
caller and the same grep found `publish` and `seo` in the same state.

**The question:** what else in this codebase was written, is referenced by
nothing, and reads like it works?

Eleven categories swept. Four held real orphans, three of which were doing
harm rather than merely sitting there.

---

## What was found

| category | result |
|---|---|
| Policy methods | 3 orphans, all in `ArticlePolicy` — fixed, see `REPORT.md` |
| Actions with no caller | **1 orphan: `UnpublishArticle`** — and it owned an event |
| Filament resources with no gate | **7 ungated, incl. the subscriber list** |
| Permissions never checked | **1: `media.manage`** |
| Blade components never rendered | **8, incl. the whole ads mechanism's only surface** |
| Publish gate rules that cannot fire | none — all 9 proven to fire, one at a time |
| Events with no listener | none |
| Events never dispatched | none |
| Jobs never dispatched | none |
| Scheduled commands | none — all four registered entries resolve |
| Columns never named in code | none (Spatie's own media columns aside) |

---

## 1. `UnpublishArticle` — an action nothing called, holding an event

`ArticleUnpublished` had a listener (`FlushArticleCaches`) and a dispatcher
(`UnpublishArticle`), so both halves looked wired. **Nothing called the
dispatcher** — not a controller, not the panel, not a test.

The panel's «أرشفة» button goes straight through `TransitionArticleStatus`,
which fired nothing. So:

    an editor archives a live story
      → status changes
      → no ArticleUnpublished
      → no FlushArticleCaches
      → no cache flush
      → the story stays on the homepage until the TTL expires

Proven against the running site, not read off the source:

    article:      #8 — شركات التقنية المالية يتجاوز مرحلة المشاريع التجريبية
    on homepage:  yes
    archived:     status is now archived
    still on the homepage: YES  <-- the cache was never flushed

**Fixed.** The dispatch moved into `TransitionArticleStatus` — the one path
that writes the column — beside the `ArticlePublished` dispatch it mirrors.
`UnpublishArticle` is deleted: its only other contribution was rejecting a
target outside `{needs_revision, archived}`, and the transition map already
allows exactly those two out of `published`.

Re-run of the same probe after the fix: `still on the homepage: no`.

Five tests in `tests/Feature/UnpublishFlushesCachesTest.php`, all driving the
transition. Deleting the action broke nothing in a suite of 837.

## 2. Seven Filament resources with no gate of any kind — including subscribers

**Filament allows anything it cannot resolve a policy for.** A resource with no
policy and no `canAccess()`/`canViewAny()` is not locked by default; it is open
to every user who can reach the panel, silently.

Category, Country, Industry, Market, Person, Topic and **Subscriber** were all
in that state. Measured, per role:

    surface                  writer   fact_checker
    SubscriberResource       OPEN     OPEN
    TopicResource            OPEN     OPEN
    PersonResource           OPEN     OPEN
    ...

A fact-checker's entire permission set is `article.view`,
`article.transition`, `article.factcheck`. They could open the newsletter
subscriber list, read every reader's email address, and **use the bulk action
that downloads the whole list as CSV**.

The docblock over `registerPolicies()` said the explicit registration existed so
a missing policy would fail loudly "instead of silently falling back to *no
policy, permission denied*". That sentence is backwards, and the seven open
resources are what the backwards reading cost.

**Fixed:**

- `App\Policies\TaxonomyPolicy` — one class for the structured half of the
  content graph, at `article.update.any`. `CompanyPolicy` and
  `OpportunityPolicy` were two verbatim copies of it and are deleted.
- `App\Policies\SubscriberPolicy` — read at `settings.manage`; create, update
  and delete are `false` for everyone, because nobody edits a subscriber by
  hand.
- `tests/Feature/Filament/PanelAccessTest.php` — the invariant: **every**
  resource has a resolvable policy or its own access check. It names the
  offender when it fails, and it fails on a resource whose policy is removed.

After: writer and fact-checker see Articles, the Dashboard and the pipeline.
Nothing else. Editor-in-chief keeps everything; the subscriber list is
editor-in-chief and super-admin only.

## 3. `media.manage` — a permission that gated nothing

Held by four roles, checked in zero places. There is no media library resource
for it to gate; uploads happen inside the article form, which is gated on
editing the article.

So the role editor offered a checkbox labelled «الوسائط» that granted no
capability, and three roles — social, video, designer — appeared to have a
permission they did not have.

**Removed**, from the permission list, from the four roles, and from the role
editor's area list. Nothing changes for anyone, which is the point: it already
granted nothing. A media library surface is in `DEFERRED.md` and brings the
permission back with something behind it.

## 4. The ads mechanism renders nowhere — NOT FIXED

`x-ads.slot` is referenced by **no template**. Nothing else reaches `AdServer`
either, so the placement lookup, the impression buffer, the click controller and
`masar:flush-ad-impressions` are all unreachable in a browser.

Underneath it:

- `ad_placements`: **0 rows**, and no seeder and no Filament surface creates one.
- `ad_campaigns`: 0 rows. The resource can create one.
- `ad_creatives`: 0 rows, and **there is no way to create one** — no relation
  manager on the campaign, no placement select, no image upload.

So the chain breaks in three places at once. I reported this as "the mechanism
shipped" in `DEFERRED.md` on 2026-09-15 and that was wrong: what shipped is the
serving half with nothing able to feed it and nowhere to draw it.

It is left unfixed deliberately — placing slots and seeding placements without
a creative editor would add more inert surface, and the creative editor is real
work that would displace the Day 6 security and accessibility passes.
`DEFERRED.md` now says what actually exists. It needs a decision about when.

## 5. Seven dead design-system components — left alone

`x-ui.link` · `x-ui.arrow` · `x-entity.person-card` · `x-entity.company-card` ·
`x-article.explore-card` · `x-data.market-row` · `x-data.stat`.

4 to 23 lines each, rendered by nothing. None duplicates a surface that was
hand-rolled instead — the profile pages use `x-entity.opportunity-card`, which
is live. Harmless, but they are seven files that look like the design system and
are not part of it. Listed rather than deleted: that is a design call.

---

## Two false positives worth recording

**`web.advertise` and `web.team` look orphaned to a grep for `route(...)`** and
are not. They are reached through the footer's `footer_company` menu, whose
items store literal URLs — `/ar/about`, `/ar/team` — in the database. Nothing is
broken, but a literal URL in a menu row is not checked by anything: change a
route and the footer keeps pointing at a 404 with no error at build, test or
request time. Worth a real check later.

**`masar:flush-views` and `masar:flush-ad-impressions` look unscheduled** to a
grep for their signature strings. They are scheduled by class name in
`routes/console.php`, and `schedule:list` shows both every five minutes.

---

## One genuine gap found on the way, not an orphan

`Subscriber::unsubscribeUrl()` has no caller. That is correct today — the only
mail the system sends is the double opt-in confirmation, and an address that has
not confirmed is not on a list to leave. But it means **a confirmed subscriber
currently has no way out**, and no message carries `List-Unsubscribe` /
`List-Unsubscribe-Post` headers.

Nobody is affected because nothing sends a newsletter yet. When the ESP is
chosen, every send must carry `unsubscribeUrl()` and both headers, and that is
now in `HANDOVER.md` rather than in anyone's memory.

---

## What would make this permanent

Only one of these categories is now guarded by a test: panel access. The rest
were found by hand-run greps that nobody will remember to run again.

The cheap generalisation is a single `tests/Feature/OrphanSweepTest.php` holding
the greps that found these — policy methods, actions, Blade components,
permissions — each as an invariant with an explicit allow-list for the ones we
have decided to keep. Recorded in `DEFERRED.md`; it is worth a slot, and it is
not worth Day 6.
