# Every number and label the site shows

Swept 2026-09-16 against the code. For each: what writes it, when, and whether
it is true.

Sorted by the decision rule:

- **A. An editor acts on it** — must be real or must go. These cause bad decisions.
- **B. A reader sees it** — must be real or must stop claiming. These cost trust.
- **C. Decorative** — probably should not exist.

**26 numbers found. 3 are wrong. 1 is unverifiable. 22 are true.**

---

## Wrong

### 1. `companies.mentions_count` / `people.mentions_count` — bucket A and B

**What writes it:** `SyncArticleEntities`, on every article save that touches
mentions, and `masar:recount`.

**When:** at save time.

**Is it true: no.** It counts **every** mention, including those in drafts and
unpublished articles. CLAUDE.md §5 says "denormalised counters count published
rows only", and `SyncArticleTopics` does exactly that — the two actions
disagree with each other.

Measured on demo data, all eight companies checked are inflated:

    company 1  held 20  published-only 17
    company 2  held 19  published-only 14
    company 5  held 10  published-only 7
    company 6  held 19  published-only 13

**Why it matters in both buckets:** it is not printed on the company page, but
`ComposeHomepage` filters the entities section on `mentions_count > 0`, so an
entity covered only in drafts can appear on the homepage as though we cover it.
And the admin's company list sorts on it.

### 2. `articles.reading_time` — bucket B

**What writes it:** `PublishArticle` persists the estimate at publish; otherwise
an accessor estimates from the body at render.

**When:** at publish, once.

**Is it true: not after an edit.** The stored value wins over the accessor, so
an article whose body grows after publication keeps the old estimate until it is
republished. Shown on ten surfaces including every card.

The estimate itself is sound — whitespace splitting rather than
`str_word_count`, which returns 0 for Arabic, and the Arabic reading speed from
config.

### 3. `articles.views_count` — bucket A and B

**Fixed 2026-09-16**, listed because it is the sweep's origin. Written by
nothing while the homepage's «الأكثر قراءة» and the admin's most-read widget
both ranked on it. Now fed by `masar:flush-views`, and **neither surface ranks
on it any more** — both read `article_views` over a rolling window.

---

## Unverifiable

### 4. The market figures — ticker, pulse, data grid, instrument grid — bucket B

**What writes it:** an editor, by hand, in settings.

**When:** whenever they choose.

**Is it true: unknowable by us, and correctly framed.** There is no feed and no
licence. Every figure carries `market.as_of`, undated figures do not render at
all, and the source line says «أرقام تجريبية — ليست قراءة سوق» in demo. The
chart is drawn without a value axis precisely so an editor-entered shape is not
presented as measured.

This is the right treatment for a number we cannot verify: it is labelled,
dated, attributed, and it disappears rather than degrading.

---

## True

### Bucket A — an editor acts on these

| number | written by | when | true |
|---|---|---|---|
| `articles.gate_failures_count` | `EvaluatePublishGate` on save/transition, `masar:recount` | save | Yes. NULL means "never evaluated", which is documented and is why `masar:recount` exists |
| Blocked-by-gate widget | reads the above | live | Yes |
| Pipeline overview (per status) | `COUNT` on `articles.status` | live | Yes |
| Published this week | `COUNT` where status + `published_at` window | live | Yes |
| Scheduled next 48 hours | `COUNT` where status + `scheduled_for` window | live | Yes |
| Most-read widget | `SUM(article_views.views)` over the window, inner join | live | Yes, since 2026-09-16 |
| `sources.consecutive_failures` | `RecordSourceResult`, the only writer | each poll | Yes |
| Source health badges | derived from the above | live | Yes |
| `intelligence_items.importance_base` + live recency | `ImportanceScorer`, `masar:reclassify` | ingest, then read-time | Yes, since the read-time split |
| Inbox navigation badge | `COUNT` where `review_state = new` | live | Yes |
| `redirects.hits`, `last_hit_at` | the redirect middleware | on hit | Yes |
| `not_founds.hits` | `RecordNotFound` job | on 404 | Yes — needs a worker, which `masar:smoke` checks |
| `ad_creatives.impressions` / `clicks` | Redis buffer + `masar:flush-ad-impressions`; clicks on the redirect | 5 min / live | Yes |

### Bucket B — a reader sees these

| number | written by | when | true |
|---|---|---|---|
| `topics.articles_count` | `SyncArticleTopics` | save | **Yes — published-only, verified.** This is the one the mentions counter should copy |
| Issue statistics (مواد / أقسام / كتّاب / دقائق قراءة) | computed live from the rendered section | render | Yes, except the reading-minutes sum inherits the `reading_time` staleness above |
| Company coverage breakdown | `EntityProfileQuery::coverage()`, published-only | render | Yes |
| Company timeline by year | `EntityProfileQuery::timeline()`, published-only | render | Yes. Hides below two years, which is why it is invisible on demo data |
| Co-mention counts | `EntityProfileQuery::coMentions()` | render | Yes |
| Person "يتحدث في" section counts | `EntityProfileQuery::sections()`, published-only | render | Yes |
| Topic "الأكثر ظهورًا" counts | `EntityProfileQuery::entitiesInArticles()` | render | Yes |
| Author published count | `PublishedArticlesQuery` count | render | Yes |
| Author "ينشر منذ" year | earliest `published_at` | render | Yes |
| Fact-check date | `articles.fact_checked_at`, set by the fact-check transition | transition | Yes |
| Opportunity deadline | editor-entered | edit | Yes, and past deadlines are styled as past |
| Key numbers on an article | editor-entered, per article | edit | Yes — and never rendered as a computed figure |

### Bucket C — decorative

| number | what it is | verdict |
|---|---|---|
| Summary-box point numbers (01, 02, …) | `$loop->iteration`, `aria-hidden` | Fine. Ordinal decoration, hidden from assistive tech, claims nothing |
| Source-list numbering | `$loop->iteration` | Fine. A citation list's numbering is its own |
| Footer copyright year | `now()->year` | Fine |

---

## What I would do, if asked

Not fixing any of these yet, as instructed. The ordering I would suggest:

1. **`mentions_count`** — it is a rule violation, it is measurably wrong on
   every row checked, and it decides what appears on the homepage. The fix is
   four lines: `SyncArticleTopics` already contains the published filter to copy.
2. **`reading_time`** — either recompute on every save rather than only at
   publish, or drop the stored column and always estimate. The second is
   simpler and cannot drift.
3. Nothing else. The remaining 22 are true, and the market figures are the
   correct treatment of a number we cannot verify.
