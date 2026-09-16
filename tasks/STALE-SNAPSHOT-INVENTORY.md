# Stale-snapshot inventory

Everything in this system that can hold a view of the world older than the
world. Listed whether or not it is currently broken, because the pattern is
systemic rather than incidental: three separate incidents so far — an empty
cache snapshot that removed the navigation and the ticker, an importance score
that froze an item's age at ingest, and a gate counter that reads NULL as
"never evaluated" — and all three looked like working features.

The question to ask of any new one: **what is the oldest this value can be, and
what tells us when it is wrong?** If the second answer is "nothing", it belongs
here with a mitigation.

Last swept 2026-09-15 against the code, not from memory.

---

## 1. Caches with a TTL

All live in Redis and survive `migrate:fresh`, a deploy and a database restore.

| what | key / tag | TTL | invalidated by | risk |
|---|---|---|---|---|
| **Settings** (whole table as one array) | `masar.settings.all`, tag `settings` | 86400s | `Settings::flush()` on write | **Has caused an incident.** An empty snapshot hid every market figure and the ticker |
| **Navigation** | `menu:{key}:{locale}:{device}`, tag `navigation` | 86400s | `FlushNavigationCache` on save | **Has caused an incident.** An empty snapshot removed the main nav for hours |
| Homepage composition | tags `homepage`, `articles`, `locale:*` | 900s | `FlushCacheTags` on publish | 15 minutes of stale front page after an out-of-band change |
| Article page | tag `articles` | 3600s | same | an hour |
| Sitemap | tags `articles`, `navigation` | 600s | same | search engines see an old list briefly |
| `intelligence:company-names` | tag `entities` | 3600s | tag flush | **Fixed 2026-09-15** — was untagged and unreachable by any flush |
| `entities:name-index` | tag `entities` | 3600s | manual flush | same shape, but tagged — this is the one built after the lesson |
| **`RobotsPolicy`** per origin | `intelligence:robots:*` | **1 hour** | nothing | **Reduced from 6h on 2026-09-15.** The only entry facing a third party: a publisher who tightens `robots.txt` waits at most an hour to be honoured |

**Mitigation that exists:** `DatabaseSeeder` ends with `Cache::flush()`, so a
fresh install is consistent. A **restore does not run application code**, so
`deploy/restore.sh` clears the cache explicitly and `masar:smoke` reads the
rendered page afterwards.

**Both open items closed 2026-09-15:** `intelligence:company-names` is tagged,
and the robots TTL is one hour. Tagging is now a rule in CLAUDE.md §6 rather
than a habit, because the property that mattered was not the hour — it was that
an untagged entry is unreachable by every flush we have.

## 2. Memoised per request

| what | scope | risk |
|---|---|---|
| **`Settings`** — the whole table in `$this->memo`, bound as a singleton | one request | anything writing settings outside the request is invisible for the rest of it. Cost a confusing test failure: seeding behind a resolved singleton left the memo holding the pre-seed state |
| `EntityProfileQuery` results | per call | none — nothing memoises across calls |

## 3. Denormalised counters

| column | rebuilt by | gap |
|---|---|---|
| `topics.articles_count` | `masar:recount` | drifts on any out-of-band insert |
| `companies.mentions_count` | `masar:recount` | same |
| `people.mentions_count` | `masar:recount` | same |
| **`articles.gate_failures_count`** | `masar:recount` | **NULL means "never evaluated", not "publishable"**. Rows created outside a save or transition — factories, seeders, an import — stay NULL and are invisible to the blocked-by-gate queue |
| `articles.views_count` | `masar:flush-views` | **Implemented 2026-09-16.** Was written by nothing while two surfaces ranked on it. Now a lifetime total fed from the Redis buffer. **Ranking no longer reads it** — the homepage section and the admin widget both rank on `article_views` over a rolling window, so a seeded number cannot rank anything |

## 4. Computed once and stored

| what | why it goes stale | status |
|---|---|---|
| **`intelligence_items.importance`** | recency decays against `now()` | **Fixed 2026-09-15.** `importance_base` holds the three durable inputs; recency is added at read time and in SQL when ordering. `importance` is kept as the ingest snapshot, for audit |
| `intelligence_items.document_type`, `classification_path` | frozen at ingest, so a keyword-table or taxonomy change leaves every stored row on the old vintage | `masar:reclassify --dry-run` reports drift; nothing runs it automatically |
| `source_items.title_simhash` | recomputed only on ingest | a change to `ArabicNormaliser` shifts the distribution under every stored hash. `DedupTest` pins exact distances so that fails loudly |
| `articles.reading_time` | computed on save | drifts if body blocks change outside a save path |
| `sources.consecutive_failures`, `last_*_at` | only written by `RecordSourceResult` | correct by construction — one writer |

## 4b. Buffered counters

| what | where it lives between writes | risk |
|---|---|---|
| `article_views.views` | Redis hash `views:buffer`, flushed every 5 minutes by `masar:flush-views` | a Redis loss discards up to five minutes of reads. Dedupe keys (`views:seen:*`) carry a 24h TTL, so a loss also lets a visitor be counted twice — both bounded, neither billed on |
| `ad_creatives.impressions` | Redis hash `ads:impressions`, flushed every 5 minutes by `masar:flush-ad-impressions` | a Redis loss discards whatever is buffered. Bounded to five minutes and nobody is billing on it — but it is the same shape as `views_count`, written down here rather than discovered later |

The flush reads and decrements per field rather than clearing the hash, so
impressions counted *while it runs* are not thrown away.

## 5. Outside the application

| what | risk |
|---|---|
| **`config:cache`** | stops `env()` being read entirely, so a changed `.env` silently does nothing. **Never run** — `deploy.sh` caches routes, views and events and a test asserts config is not among them |
| `public/build` | content-hashed and now committed, so a commit and its bundle cannot disagree. A rebuild deletes the previous hash, so an already-open tab 404s on its assets — visible in the 404 log |
| Sessions in Redis | survive a database restore, so a live session can hold a user id that no longer exists |
| Redis, test vs development | **Fixed 2026-09-16.** The suite ran on the development Redis because `CACHE_STORE=array` does not touch the Redis facade, so test article ids reached the development view buffer and dedupe keys with a 24-hour TTL outlived `RefreshDatabase` — a run could pass or fail on what ran yesterday. Tests now use Redis databases 14/15, flushed once at boot, and `Tests\TestCase` refuses to run if the numbers did not take effect |
| **Queued jobs in Redis** | survive `migrate:fresh`. Caused 45 permanently failed jobs. Fixed by removing the model from the payload; §3 carries the rule for every job written from here on |
| OPcache with `validate_timestamps=0` | a deploy that does not reload PHP-FPM keeps running the old code. `deploy.sh` reloads, and `masar:smoke` would catch it |

## What has caught these

Nothing in the test suite, structurally — tests run on the `array` cache driver,
so this whole first section is invisible to them by construction.

What catches them is `masar:decay` and `masar:smoke`, both of which read the
rendered page rather than the database. That is the only reason the navigation
incident was found at all, and it is why neither may be weakened into a query.
