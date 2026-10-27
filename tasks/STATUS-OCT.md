# MASAR — status after the gap

Audit date: 2026-10-27. Last working session: 2026-09-16 (the brief says the 15th;
the last commit, the last REPORT entry and the last database write are all the 16th).

Every figure below was measured today. Where a document and the code disagree,
the code is reported and the document is named.

---

## Read this first — the development database server is dead

**MySQL on port 3307 does not start, and has not since at least 15 October.**

- `.env` and CLAUDE.md §9 point at Homebrew `mysql@8.0` on 3307. Its binary
  (8.0.46) links `libprotobuf-lite.35.1.0.dylib`. Homebrew installed `protobuf`
  36.2 on **2026-09-23 01:37**, which removed that library. Every start since
  ends in `dyld: Library not loaded`. The error log holds **25,587** failed
  attempts. The last successful start was 2026-09-21, and there is no clean
  shutdown after it.
- A separate `mysql@8.4` (8.4.11) was set up on **port 3308** on the same night.
  It holds another project's databases (`photographer`), not MASAR's. It is
  recorded nowhere in this repository.
- **The data is intact.** I copied `/opt/homebrew/var/mysql` into the scratchpad
  and opened the copy with the 8.4.11 binary on a throwaway port. It recovered
  from the unclean stop, upgraded its data dictionary (8.0.46 → 8.4.11) and
  served `masar` exactly as it stood on 16 September. **The original directory
  was not touched.** Everything in §2 that needs a database was measured on that
  copy. The copy has since been stopped and deleted.

Consequence: today, on this machine, **the site, the admin, the suite,
`masar:smoke`, `masar:decay` and `masar:recount` all fail with
`Connection refused`.** Nothing is fixed; see §5 for the order I would do it in.

---

## 1. Where the 10-day plan actually stopped

Your record says Day 7 was next and Days 8–10 were untouched. **Day 7 had started,
Day 6 is not fully closed, Day 1–2 only finished its laptop half, and Day 10's
handover had begun accumulating.**

| slot | state | measured |
|---|---|---|
| Finish Intelligence (was Day 1–2) | **complete** | create-draft, `masar:reclassify --dry-run`, restore-tested decay, inventory all present |
| Day 1–2 Deployment, domain-free half | **partial: code yes, server no** | Built: demo-mode switch (on: `noindex`, `robots.txt Disallow: /`, sitemaps 404, banner), `deploy/` (provision, deploy, backup, restore, nginx, php-fpm, supervisor), `masar:smoke`, R2 failing loudly, security-header snippet. Backup/restore tested **locally only**. **Never done:** a VPS (blocked on host and user since 15 Sept), Sentry or any error tracker (none in `composer.json`), uptime monitoring, a Lighthouse run from a real server, a restore test on a real server |
| Day 3–4 Unfinished pages | **complete** | company, person, author, topic, issue, team, corrections, advertise views exist. Economy and podcast struck by decision. Opportunity filters already SQL-side. I rendered category, markets and an opportunity page today; I did not re-crawl every page |
| Day 5 Notifications and control layer | **complete, with one hollow item** | Read-time importance, in-app and email notifications, composer ordering in the UI: present. **Ads:** the serving mechanism exists, but `x-ads.slot` is referenced by **0** templates, and there are **0** placements and **0** creatives, with no admin surface to create one. REPORT.md says so itself (orphan sweep) |
| Day 6 Hardening | **mostly complete. REPORT.md says "Day 6 closed"; three plan items were never done** | Done: numbers sweep, commitments sweep, orphan sweep, JSON-LD stored XSS fixed, policies on every resource, axe on 19 pages × 2 viewports, focus rings, 24px targets, video text alternatives. **Not done:** (a) **404/500 pages designed.** Measured: `/ar/no-such-page` returns Laravel's stock English «Not Found» page, and `resources/views/errors/` does not exist. (b) **Lighthouse on every page type from the production server.** No server. (c) **Mobile 400px sweep of every page.** No record of it anywhere in REPORT.md |
| Day 7 Demo content | **started, uncommitted, unreported** | Bullet 1 is done: `DemoContentSeeder::ARCHIVE_SHAPE` spreads published articles across 2021–2026, and the dev database shows `{2021:2, 2022:5, 2023:7, 2024:10, 2025:16, 2026:32}`. That change is uncommitted and appears in no report. At 19:28:44 and 19:29:48 on 16 Sept, **all 97 articles** on the dev database were rewritten in place (`updated_at`), which is the re-dating applied out of band rather than by a reseed. **Not started:** the 10–12 full-model articles (all 97 still carry template headlines such as «… ينتقل من التوسع إلى الربحية»), any "demo" marking in the admin (no column exists), a complete issue or report as an entity (no tables), the content graph built around those articles |
| Day 8–9 Domain cutover | **not started. Confirmed** | `MAIL_MAILER=log`, no Resend or Postmark package, no `List-Unsubscribe` header anywhere, `Subscriber::unsubscribeUrl()` still has no caller, `polling_enabled` is false, no `/bot` route, no DNS, SSL or SPF/DKIM/DMARC work |
| Day 10 Handover | **not started as a day, partly accumulated** | `tasks/HANDOVER.md` exists: 105 lines, started 16 Sept, with a "Queued for this document" list. **0** git tags. No final STATUS.md. The six how-tos the plan names (run, deploy, add an editor, publish, change the homepage, add a source) are not written |

One correction to the documents: **`HANDOVER.md` is at `tasks/HANDOVER.md`, not
the root.** The brief named it as a root file.

---

## 2. Measured state

| check | result |
|---|---|
| **Suite** | **866 passed, 2,956 assertions, 44.43 s**, 0 skipped or risky, on the copied database under **MySQL 8.4.11**, not the 8.0 the brief records. The last report said 858. **Against the configured database it cannot run.** |
| **`migrate:fresh --seed`** | **clean on a scratch database: 4,476 rows**, 42 s (the transcodes were cached). STATUS.md recorded 4,477 on 14 Sept. |
| **`masar:smoke`**, real config | **crashes.** "homepage did not render", then an uncaught `QueryException`. No table, no diagnosis. |
| `masar:smoke`, on the copy | 7 of 8 pass. **FAIL: queue worker not running** (depth 0, failed 0). Demo mode ON as expected. |
| **`masar:decay`**, real config | **crashes** at the queue check with an uncaught `QueryException`. No table is printed, and the remedy hint never runs. |
| `masar:decay`, on the copy | no FAIL. Queue WARN (worker not running). All 8 flows ok, 5 public forms addressed. |
| **`masar:recount --dry-run`**, on the copy | **0 rows out of date** on all four counters (`companies.mentions_count`, `people.mentions_count`, `topics.articles_count`, `articles.gate_failures_count`). Nothing written. |

### Git

- **Last commit: 2026-09-16 13:38.** Everything after it is uncommitted: 52
  modified, 4 deleted (3 of them staged), 22 untracked. Among the untracked files
  are the **stored-XSS fix** (`app/Support/JsonLd.php`), the **policies that closed
  the subscriber-list leak** (`SubscriberPolicy`, `TaxonomyPolicy`), `SafeUrl`, the
  security-header snippet, `PanelAccessTest` and the whole `tests/Feature/Security/`
  directory. If this working tree is lost, the XSS and the open subscriber list come
  back.
- **The remote history was rewritten.** `origin/main` (3453000) and local `main`
  (44c0531) share **no common ancestor**. The trees are byte-identical; what differs
  is authorship. The local commits are by `buildwithimad <kimad1728@gmail.com>`, the
  remote's by `Mohamed Abdo`. The remote was force-updated at some point after
  16 Sept, and this machine fetched the rewrite today at 17:48. **A plain
  `git push` will now be rejected as non-fast-forward.** Do not force-push over it;
  see §5.
- The commit messages are all «creat new projedt». CLAUDE.md §6 requires scoped,
  imperative messages.

---

## 3. What decayed in six weeks

Ordered by how much it would hurt.

1. **The database server**: above.
2. **Both health checks die on the exact failure they exist to report.** With the
   database unreachable, `masar:decay` and `masar:smoke` throw instead of recording
   a FAIL row and printing their tables. Exit code 1 is the only signal. This is
   the §7 rule ("a health check must observe the artefact") failing one layer
   down: the checker itself needs the database it is checking.
3. **No worker, no scheduler, no Horizon has run since 16 Sept.** Three effects:
   - **Four scheduled articles are overdue**: #73 (due 16 Sept), #74 (26 Sept),
     #71 and #72 (29 Sept). All pass the gate. The first scheduler tick will
     publish all four in the same minute, stamped `now()`.
   - `views:buffer` in Redis still holds **2 reads from 16 Sept**, never flushed
     into `article_views`.
   - The queue itself is empty (depth 0, failed 0), so no orphaned jobs.
4. **The ticker is six weeks old and still renders.** `market.as_of` is
   `2026-09-14 18:07`. The §5 rule (labelled, dated, attributed) is met to the
   letter, but nothing enforces a maximum age, so the front page shows
   six-week-old figures to anyone who opens it. Whether a dated figure should
   expire is your call, not a bug I can rule on.
5. **Five "open" opportunities passed their deadlines during the gap**: ids 11,
   7, 13, 2 and 3, closing between 3 and 23 Oct. None had lapsed by 16 Sept. The
   card greys the date, but `scopeOpen()` reads `status = 'open'` only, so the
   «مفتوحة فقط» filter still lists all five as open.
6. **Every time-windowed surface is now empty**: 0 articles published in the
   last 7 days (the newest published article is from 1 Sept), 0 scheduled in the
   next 48 h (only the 4 overdue), 0 reads in 30 days (so «الأكثر قراءة» hides
   itself, correctly), and 0 of 114 inbox items under 48 h old, so read-time
   recency now contributes nothing to any score. All of this is correct
   behaviour, but the demo looks like an abandoned site until it is reseeded or
   re-dated.
7. **Cache: nothing stale, by expiry.** The longest TTL in the inventory is 24 h,
   so every value entry had expired. Only nine `cache-tag:*:entries` index sets
   remain, with no TTL. They are harmless, but they accumulate.
   **Disclosure:** my seed run's `Cache::flush()` cleared those, and my renders
   wrote fresh TTL'd entries and ten 2-hour session keys into the dev Redis.
8. **A seeder time bomb (uncommitted code).** `ARCHIVE_SHAPE` hard-codes `2026`
   as the current year. A seed run after 1 January 2027 puts the 30
   "most recent" articles in the previous year, leaving only the 2-article
   overflow in the current one.
9. **An unexplained write to the dev database.** User #5, `ohasan@example.org`, a
   Faker-style address with **no role**, was created at **2026-09-16 19:28:16**:
   28 s before the 97-article rewrite and after the last REPORT entry. Nothing
   records it. It is either a probe from that evening or something wrote to the
   dev database from a factory. The second is the incident `phpunit.xml` already
   warns about.
10. **No account can administer the panel.** There is no `super_admin` user (the
    owner account was lost on 14 Sept, and `HANDOVER.md` says `masar:owner` must
    be re-run). **No user has 2FA confirmed**, so with
    `EnsurePublishersUseTwoFactor` in place, every publisher's first login is a
    2FA enrolment.
11. Expired tokens: the 14 `pending` subscribers are seeded rows, and any
    confirmation link would have expired after 7 days. Unsubscribe links never
    expire, by design. No real person is affected.
12. **The environment changed with no record.** The 3307 → 3308 split, a second
    MySQL major version, and Homebrew upgrading a library under a running
    database. §9 says "Never change these without saying so"; this happened
    outside the project, which is why nobody said so.

What did **not** decay: counters (0 drift), the media disk (12,028 files, all
present), built assets (manifest matches disk), demo mode (still ON, site not
indexable), `robots.txt` (still served by the route), the menu-to-route links, and
the publish gate.

---

## 4. Blockers that need you

Your list, confirmed, with what each one unblocks:

| blocker | blocks |
|---|---|
| **Domain** | Day 8–9 entirely: SSL, SPF/DKIM/DMARC, the `/bot` page, enabling the crawler |
| **VPS**: host and a sudo user with my key, firewall 22/80/443, `/root/masar-secrets.env` mode 600 | Day 1–2's server half, every production Lighthouse number, the real restore test. Spec is in REPORT.md "What I need from you — server spec" |
| **ESP**: Resend or Postmark | the newsletter's sending half, `List-Unsubscribe` and one-click, then 2–4 weeks of IP warming before any bulk send |
| **Contact addresses**: `contact.editorial_email`, `advertising_email`, `careers_email`, `general_email`, plus the crawler's `contact@masar.sa` | the corrections, advertise and about pages, which currently route readers to an empty contact page; the crawler gate |
| **Editorial team** | real accounts (no owner exists today), 2FA enrolment, the 10–12 Day 7 articles, the manual regulator watch (§5: no Saudi regulator publishes a feed) |

Missing from your list:

| blocker | why it needs you |
|---|---|
| **The git history rewrite** | Someone with push access rewrote `origin/main`'s authors. Before anything is pushed I need to know: was that you, and is the remote now the canonical history? Also: who is `buildwithimad`? Every local commit carries that identity |
| **R2 credentials**: account id, access key, secret, bucket, endpoint | production media. Without them `MEDIA_DISK` cannot be `r2`, and §2 forbids media on the app server |
| **Sentry DSN, or "skip"** | the error-tracking half of Day 1–2 |
| **The Day 6 leftovers**: 404/500 design and the 400px sweep | 404/500 is mine to build, but it needs a decision on tone and copy for an Arabic error page |
| **Stale-figure policy** | whether a dated market figure or an "open" opportunity should expire on its own (§3, items 4 and 5) |
| **The ads surface** | build the creative editor and place the slots, or strike ads from launch scope. Today it is a mechanism with no surface, which is the pattern this project keeps paying for |
| **A larger hero master**, and re-sourcing the three dropped video clips | carried from 14 Sept, still open |

---

## 5. What I would do first if we restart tomorrow

My order, and the reason for each.

1. **Commit the working tree, before anything else touches this machine.**
   Three weeks of security work exist only as uncommitted files on one laptop
   whose database server has already died once without anyone noticing. Commit
   in scoped pieces (§6), then reconcile with the rewritten remote: the trees are
   identical, so it is a `reset --soft origin/main` plus new commits on top, not a
   force-push. **I need your answer on who rewrote the remote first.**
2. **Restore the database server, then re-record §9.** Either reinstall
   `mysql@8.0` against the current protobuf or move MASAR onto 8.4 deliberately,
   on purpose rather than by accident. The copy shows 8.4.11 opens the data and
   the suite passes on it, and 8.4 LTS is the better production match. Then
   update §9 (port, version) and `phpunit.xml`, which force-pins 3307.
3. **Make `masar:decay` and `masar:smoke` survive a dead database.** Each check
   catches its own failure and records a FAIL row, with the remedy line. A health
   check that crashes on the outage it exists for is the most expensive kind of
   false confidence, and fixing it is about an hour.
4. **Run the decay checks against the live state, then deal with the four overdue
   articles before starting the scheduler.** Otherwise the first tick publishes
   four stories at once with today's date, which is not an editorial decision
   anyone made.
5. **Then the VPS, the moment you send a host.** It has been the longest-lead
   item since 15 Sept, it is the only way to get a trusted LCP for any page, and
   `public/robots.txt` already showed that production-only failures exist.
6. **Close Day 6 honestly**: the designed 404/500 and the 400px sweep. Both are
   small, both are things a reader meets, and REPORT.md currently says the day is
   closed when it is not.
7. **Then Day 7, with the editorial team rather than without it.** The remaining
   Day 7 work is writing ten real articles to the full model. That is editorial
   work, and it is better done with the people who will own the product than by me
   generating more of the 97.

Days 8–9 wait on the domain and the ESP. Nothing in them can start before those
arrive, so they should not be on tomorrow's list at all.

---

## Corrections made during this audit

One, at your explicit request: **CLAUDE.md §9, the LCP row.** It said the category
page at 3725 ms was "the one nobody has examined". §5 records the fix measured on
that same page. The code and the rendered pages agree with §5: every category
shape and markets carries exactly one non-lazy `fetchpriority="high"` image, and
every other image is lazy. §9 was the stale row. It now says the 3725 figure
predates the 15 Sept fix and that no clean post-fix number exists.

`tasks/STATUS.md` §5 and §8.4 make the same "nobody has looked at it" claim. I
left them, because that file is a dated snapshot of 14 Sept and was true when
written.

Nothing else was changed. The original MySQL data directory was not opened. The
seed check briefly added 869 files under `storage/app/public/media` (scratch
media ids collide with dev ids on the shared disk). I restored the directory from
a clone taken beforehand, and verified it identical by checksum (12,028 files).
Every decay run with `--with-seed` will do the same, and nobody had noticed.
