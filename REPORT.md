# MASAR — task report

Date: 2026-09-15. Covers the four rulings on the `tasks/STATUS.md` audit, plus
the process change.

---

## Read this first

**I destroyed the development database during this task, and restored it.** The
decay-check command I built runs `php artisan test` as a subprocess. PHPUnit
does not override an environment variable that is already set, and a booted
Laravel process carries `DB_DATABASE=masar` from `.env`, so the child ran
`RefreshDatabase` — which is `migrate:fresh` — against the **development**
database. Articles, media, subscribers and the inbox all went to zero.

Re-seeded and verified: 97 articles, 206 media, 60 subscribers, 22 inbox items,
7 sources, 19 homepage sections. Demo content is reproducible, so nothing of
editorial value was lost.

**What was not reproducible: your owner account and its 2FA enrolment.** It was
created with `masar:owner`, not by the seeder, so the reseed did not bring it
back. You need to run one command and re-scan the QR code:

    php artisan masar:owner mohamedabdo2002815@gmail.com

It prompts for the password rather than taking it as an argument. The four
seeded editorial accounts are back; the owner is not.

Two guards now exist so this cannot recur:

- `Tests\TestCase` refuses to run against any database whose name does not
  contain "test", and says why. Verified by pointing the suite at `masar` on
  purpose: it aborts instead of migrating.
- `phpunit.xml` marks the `DB_*` and environment vars `force="true"`. Measured
  honestly: **this alone does not close the hole** — a shell-set `DB_DATABASE`
  still wins under `php artisan test`. The guard is the protection; the
  attributes are defence in depth. The comment in the file says so.

---

## 1. Newsletter — built and working end to end

`/ar/newsletter` and the band that appears on **every** page both posted
nowhere. Correction to my own audit: I reported one dead form. There were two —
`components/layout/newsletter-band.blade.php` is in the public layout, so the
dead form was on every page of the site, not one.

Built: POST route, Form Request, honeypot, rate limit, double opt-in,
confirmation mail, unsubscribe, and `visitor_id`.

| piece | where |
|---|---|
| POST route + confirm/unsubscribe | `routes/web.php`, above the `{category}` catch-all |
| Validation | `app/Http/Requests/SubscribeToNewsletterRequest.php` — the first Form Request in the codebase |
| Honeypot | `company_website`, must be present and empty |
| Rate limit | `throttle:newsletter` — 5/min and 20/day per IP; `search` limiter added too |
| Double opt-in | `SubscribeToNewsletter` → `ConfirmSubscription`; signed link, 7-day expiry |
| Confirmation mail | `ConfirmNewsletterSubscription`, queued |
| Unsubscribe | Signed link, never expires |
| `visitor_id` | `IssueVisitorId` middleware — first-party ULID cookie, httpOnly, SameSite=Lax, 400 days |

Driven over real HTTP against the dev server, not only in tests: scraped the
CSRF token from the page, posted the form, Horizon processed the queued
notification, the mail landed in the log, and clicking the real signed link out
of that mail confirmed the subscription.

    email: live-probe@example.com   status: pending -> confirmed
    visitor_id: 01M2GDJXKBEXPV1QBQ8FQAX12C
    verified_at: 2026-09-14 19:57:03

Three decisions worth your attention:

- **The form tells you nothing about the address.** New, already pending,
  already confirmed and previously unsubscribed all produce the same sentence
  and the same redirect. Anything else makes it an oracle for "does this person
  read MASAR". A test asserts the two responses are byte-identical.
- **Unsubscribe is a GET that does nothing plus a POST that acts.** Mail
  clients and scanners fetch every link before a human sees it, and a GET that
  unsubscribed would drop real readers. This is also the shape RFC 8058
  one-click expects.
- **A 15-minute per-address cooling-off window** on the confirmation mail, in a
  new `confirmation_sent_at` column. Double opt-in otherwise lets a stranger
  post mail to an address they do not own; a per-IP limit does not close that,
  because the cost lands on the address.

Also fixed while there: the mail template was **LTR with English chrome** in an
Arabic-first product. `resources/views/vendor/mail` now carries direction from
the locale, and `lang/ar.json` translates Laravel's own strings. Verified on the
rendered message: RTL yes, "All rights reserved" gone, subcopy Arabic.

17 tests.

---

## 2. Queue — Horizon installed, backlog drained, and it broke

**Horizon was not installed at all**, despite §2 naming it in the production
stack. It is now, and its dashboard is locked down: the packaged gate is
`Gate::check(...) || app()->environment('local')`, which opens it to anyone who
can reach the port on a dev machine, and job payloads here contain subscriber
email addresses. `HorizonServiceProvider` drops that escape hatch, so the answer
is `super_admin` in every environment. A guest request returns 403.

The 1,771 backlog was **1,754 `FlushArticleCaches` + 17 `RecordNotFound`**.
Drained. What broke, exactly as you predicted:

**45 jobs failed permanently** with `ModelNotFoundException` on article id 98,
when the highest id that exists is 97. They were queued against a database that
a later `migrate:fresh` replaced — Redis is not rebuilt with the schema, so
every job already in the queue is orphaned.

The first fix was wrong and I caught it by testing that it failed without the
fix. `$deleteWhenMissingModels` **does nothing on a queued listener**: the queue
reflects on the resolved job class, which for a listener is Laravel's
`CallQueuedListener`, never yours. It looked like protection and was not.

The real fix removes the model. `FlushArticleCaches` is no longer queued — it
resolves the cache tags inline, while the article certainly exists, and hands a
list of **strings** to a new `FlushCacheTags` job. A job carrying no model
cannot be orphaned.

Two other things the drain revealed:

- **404 logging works** once a worker runs — 11 paths recorded. Among them
  `/build/assets/app-CXaz7BH-.js` with 5 hits, which I checked and is **not** a
  live defect: the manifest points at `app-DDXQg1Xu.js`, which exists. Those
  were stale browser tabs holding a previous build hash. Worth knowing the log
  will show that after every deploy.
- **The test suite crossed PHP's 128M CLI memory limit** at 667 tests and died
  mid-run in a file that passes alone. `tests/Pest.php` now raises it to 512M so
  the suite does not depend on the machine's php.ini.

4 tests, including one that drives a real worker rather than inspecting a flag.

---

## 3. Intelligence inbox — built, on a different model than you named

**I built it on `IntelligenceItem`, not `SourceItem`.** `SourceItem` holds the
publisher's own prose, and §5 says that text is internal for its whole life —
"never placed in a Filament field an editor can copy from". `IntelligenceItem`
is documented in its own class comment as "one row in the editorial inbox" and
carries only facts about the publication plus our own writing. Putting the
surface on `SourceItem` would have violated the rule the two tables exist to
enforce. A test asserts the raw body never reaches the page, and proves the
prose was in the database first so the assertion is not vacuous.

`app/Filament/Resources/Intelligence/IntelligenceItemResource.php`:

- Opens as a queue, filtered to `new`, ordered by importance then recency.
- Navigation badge counts only untriaged — it will read **22** for you now.
- Row actions: approve, save for later, reject, ignore, restore, open source.
- Bulk reject and bulk ignore.
- Restore is the recovery half of the 30-day window, and clears `reviewed_at` —
  the column the purge scope reads — so a restored item cannot be purged while
  sitting in the queue.
- No create, no edit. An editor triages what the pipeline found.

New permission `intelligence.triage`, held by `super_admin`, `editor_in_chief`,
`editor` and `researcher`. Triage is daily editorial work; `intelligence.configure`
is about sources and is a different thing.

Fixed on the way: `SubscriberResource` used `fn (string $s)` in
`formatStateUsing`. Two faults — a parameter name Filament does not recognise
(§6), and a `string` type that the new enum cast would have broken at runtime.
The existing test that enforces closure names only covers `Builder $query`, so
it did not catch this.

**On the threshold you asked about — confirmed live, not faked.** Drove a
throwaway source to failure with a real worker running:

    attempt 1-4 -> failing, still active
    attempt 5   -> DISABLED, 2 notifications written
    attempt 6   -> still disabled, still 2 notifications

Disables at exactly 5, notifies both eligible recipients once, and does not
re-notify on later failures. The mail genuinely sent (verified in the log by
body text — subjects are MIME-encoded, which is why my first grep found
nothing). Probe source and its notifications removed; 7 sources, 0
notifications.

12 tests.

---

## 4. CLAUDE.md corrections

- **§2 reader accounts** — corrected. There is no `reader` guard in
  `config/auth.php`; the row claimed one was scaffolded.
- **§2 hosting** — Horizon is a real dependency now, with the gate documented.
- **§9 homepage LCP** — marked **SUPERSEDED**. The 3685 ms figure sits beside
  the 2026-09-14 re-measure (home 2053, article 2238, markets 2832, category
  3725) with the note that the likeliest explanation is a harness without
  compression, and that neither number should be quoted as settled until one
  clean run replaces the row. The reasoning is kept.
- **§3** — two new rules from the queue work: a queued job must not carry a
  model it may outlive, and `$deleteWhenMissingModels` does not work on a
  listener.
- **`integrations.analytics_id` removed** from config and the settings screen.
  Nothing read it. Two tests used it as their example key and now use a setting
  that exists.

**`integrations.newsletter_key` is in exactly the same state** — declared,
encrypted, read by nothing. I left it, because removing it is a product
decision: the newsletter I just built is self-hosted and needs no provider key,
so if you are not planning an ESP it should go the same way. Your call.

---

## 5. Process change — the decay check

`php artisan masar:decay` exists and is documented in CLAUDE.md §7.

It found something real on its first run: a form in `opportunities.blade.php`
with no action. That one is a working filter — the controller reads every
parameter — but I gave it an explicit action rather than weakening the check,
because "every form states where it goes" is what makes this catch the
newsletter class of bug without reading each file.

Two honest limits, both measured:

- **The suite is not run from the command**, and the reason took me three tries
  to get right. I first wrote that Pest deadlocks when launched from PHP, then
  that a stale process was holding the test-database lock. Both were wrong.

  The actual cause: **`php artisan test` hangs when its output is read through
  a pipe.** The suite finishes — Pest prints `679 passed · Duration 50.22s` —
  and then the process sits at 0% CPU indefinitely, because something spawned
  during the run inherits stdout and outlives Pest, so the reader never sees
  EOF. One run was left sitting for two hours and one minute after passing.
  Redirecting to a file instead exits in 0s with the identical result.

  Laravel's `Process::run()` captures output through the same kind of pipe,
  which is why every attempt to shell out to the suite "hung". Recorded in
  CLAUDE.md §9 with the workaround. **Run the suite as
  `php artisan test > /tmp/suite.txt 2>&1`, not piped.**

- **`migrate:fresh --seed` is opt-in** behind `--with-seed`. It transcodes video
  and normalises every demo photograph and takes about 15 minutes — longer than
  the pulse it was supposed to be part of.

---

## Decay check

`php artisan test` — **679 passed, 2,234 assertions, 50.22s**, zero skipped.

| check | state | detail |
|---|---|---|
| suite | ok | 679 passed (2,234 assertions) in 50.22s |
| queue | ok | depth 0 · failed 0 · worker running |
| migrate:fresh --seed | SKIP | not run — use `--with-seed` (~15 min) |
| flow: publish an article | ok | 72 published |
| flow: article on the homepage | ok | 19 sections |
| flow: navigation renders | ok | 23 menu items |
| flow: homepage order is editable | ok | 19 distinct positions |
| flow: newsletter stores a subscriber | ok | POST route present |
| flow: source poll reaches the inbox | ok | 7 active sources · 22 inbox items |
| flow: redirect becomes a 301 | ok | 0 redirects configured |
| public forms | ok | 5 forms, all addressed |

Tests went 646 to 679. The 33 new ones are the newsletter (17), the inbox (12)
and the queued-listener resilience (4).

---

## Over to you

1. **Re-register your owner account** — `php artisan masar:owner mohamedabdo2002815@gmail.com`,
   then re-scan the 2FA QR. My fault, explained above.
2. **Re-source three video clips.** All three stayed over the 10 MB budget after
   a 1080p pass, so none was committed. Masters are untracked in
   `storage/app/video-masters/`:

   | clip | master | after transcode |
   |---|---|---|
   | `9339478-uhd_3840_2160_24fps.mp4` | 167.4 MB | 23.05 MB |
   | `10407687-hd_3840_2160_30fps.mp4` | 101.2 MB | 24.18 MB |
   | `19745264-uhd_3840_2160_60fps` | 25.2 MB | 20.43 MB |

   Prefer 1080p-native, 24–30fps, under ~30 seconds. The 60fps one is the worst
   offender per second of footage.
3. **Decide on `integrations.newsletter_key`** — keep it only if an ESP is coming.
4. **The push is still clear.** Nothing in this round added a large file; the
   largest tracked object remains a 6.33 MB photograph.

Next by the order you adopted: the category page at 3725 ms, then one real RSS
feed through the pipeline, then TASK 07 C to F.
