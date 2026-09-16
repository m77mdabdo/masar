# MASAR — Completion Plan to 25 September

Read `CLAUDE.md` first. This is the final push. Ten working days.

## The goal, stated precisely

A **complete platform with demo content**. Every feature built, wired and working.
Real editorial content comes after handover — that is not a gap in this plan, it is
the plan.

"Complete" means: an editor can do the whole job in the admin, a reader can use the
whole site, nothing renders a placeholder, nothing silently does nothing.

## One rule that holds throughout

The 97 demo articles are AI-generated. They may run on a server. They may **not** be
indexed under the Masar brand on an open public domain — if Google archives generated
copy under this masthead, it attaches to the brand before a single real word is
published.

So: `noindex` site-wide until real content exists, a visible "نسخة تجريبية" banner, and
`robots.txt` disallowing everything. All three removable with one setting when real
articles land. Build the switch, default it to closed.

---

# Day 1-2 — Deployment, the half that needs no domain

On an IP or a temporary hostname. This is the longest-lead, never-done work and
it is what makes Day 6 able to measure anything real.

- Provision the VPS: Ubuntu 24.04, Nginx, PHP-FPM 8.4.1+, MySQL 8, Redis, Supervisor
- Horizon running as a supervised process, not a terminal you left open
- Deploy script — no manual FTP, ever
- gzip/brotli at the edge (§9 records this as worth ~1.9s and a deployment
  requirement, not a middleware workaround)
- Backups running, and **one restore actually tested**. An untested backup is not
  a backup — and §9 now records that a restore must be followed by `cache:clear`,
  which the restore test must prove.
- Sentry or equivalent, plus uptime monitoring
- `APP_ENV=production`, `APP_DEBUG=false`, no secrets in the repo
- The demo-mode switch: site-wide `noindex`, a visible «نسخة تجريبية» banner, and
  `robots.txt` disallowing everything. All three off one setting, defaulting closed.

Report the URL, the restore test result, and the first Lighthouse run from the
real server. Those numbers will differ from every figure we have.
---

# Day 3-4 — The unfinished pages

Every page that currently renders thin or routes somewhere generic.

- ~~Economy~~ struck — no such category, and there should not be. The launch three
  are saudi, business, insights. Category pages now choose their shape from their
  own supply rather than sharing one template with holes in it.
- Company profile — the payoff for the whole content graph. Profile, aggregated
  content across all types via `EntityContentQuery`, timeline.
- ~~Opportunities index with SQL-side filters~~ — already done; all five filters are
  `when()`/`whereHas()` clauses narrowing before pagination.
- Person, Author, Topic pages given the same care
- Reports and Issues — the sections that currently under-fill. **Podcast removed**
  from the site entirely; there is no podcast and there will not be one by Day 7.
  See DEFERRED.md.
- Every "view all" link goes somewhere real

Nothing may render an empty section or a dead link.
---

# Day 5 — Notifications and the control layer

- **Compute intelligence importance at read time.** `recency` decays against
  `now()` over 48 hours and is then *stored*, so every score freezes an item's
  age at ingest and the recency weight does nothing — a third of the corpus
  drifted in a day. Move the calculation to read time, keep the stored
  components for auditability, and add the column to the stale-snapshot
  inventory. Same family as everything else caught here: plausible output,
  broken mechanism.

- In-app notification centre in the admin
- Email alerts for critical intelligence rules
- Ads and sponsored content: placements, campaigns, the sponsored label that cannot
  be removed from a post
- Confirm the homepage composer exposes section ordering **in the UI**. If reordering
  still requires a seeder edit, that's a hole in the control layer and the entire
  point of TASK 04 was that the owner doesn't need a developer.
---

# Day 6 — Hardening

- **Sweep every number and label the site shows a reader**, and for each say
  **what writes it, when, and whether it is true.** Report the list rather than
  fixing as you go — the decision about which earn an implementation and which
  should stop claiming is the owner's.

  At minimum: reading time · article counts on topics and categories ·
  «الأكثر قراءة» · trending topics · mentions counts on company and person
  pages · issue statistics · anything in the admin dashboard an editor might
  act on.

  Two of these have already turned out to be claims with nothing behind them —
  `views_count` was written by nothing while two surfaces ranked on it, and the
  podcast section presented interviews under a heading promising audio.

- **Sweep every public commitment against what the code enforces.**
  `/ar/advertise` promised the sponsored label appears "in every place it
  appears on the site" and it appeared on one surface out of seven. The
  editorial standards, about, contact, corrections and team pages all make
  claims about how this publication behaves — fact-checking, corrections,
  attribution, sourcing, the publish gate. Check each claim against the code
  and report **which are promises we keep and which are promises we intend**.
  The second kind needs rewording, not code.
- Security pass: rate limits, CSRF, upload validation, headers, 2FA enforced
- Every form: validation, error states, success states, honeypot
- Every 404 and 500 page designed rather than default
- Full axe pass across every page type, not four
- Lighthouse across every page type from the production server
- Mobile at 400px on every page — this has been spot-checked, not swept
---

# Day 7 — Demo content that shows the product properly

**Date the content across years.** All 72 seeded articles currently fall in
2026, so the company profile's coverage timeline has one bar and correctly hides
itself — a one-bar chart states nothing. The component is built and tested; it
needs content with a history to draw. This is a demo-content requirement, not a
defect, and it is the kind of thing that gets missed because the page looks fine.

The content is demo, but it should demonstrate what MASAR is.

- 10-12 articles written to the full editorial model: real structure in the four
  questions, real key numbers, real sources cited, hero images that match subject
- Mark them clearly as demo in the admin
- Every section on every page fills from them
- One complete issue, one complete report, three videos, three podcast episodes
- Companies, people and opportunities that connect to the articles, so the content
  graph visibly does something

This is the difference between a demo that shows a CMS and one that shows a magazine.
---

# Day 8-9 — Domain cutover

Everything that could not happen without the domain existing.

- DNS and SSL on the real domain
- SPF, DKIM and DMARC
- **The newsletter's sending half.** Subscribe, confirm and unsubscribe are built
  and working; only sending waits here. Pick the ESP — Resend or Postmark — wire
  it, send a real test to a real inbox, and confirm it arrives, renders Arabic
  RTL, and the unsubscribe link works. Note in the handover that IP warming takes
  2-4 weeks before any bulk send.
- **Enable the crawler.** `masar.intelligence.polling_enabled` stays false until
  `https://masar.sa/bot` serves a page explaining what the bot is and how to
  block it, and `contact@masar.sa` reaches a human. Both are conditions, not
  formalities — §5 carries the rule.
---

# Day 10 — Handover, plus slack for anything that slipped

- `HANDOVER.md`: how to run it, how to deploy, how to add an editor, how to publish,
  how to change the homepage, how to add a source
- `tasks/STALE-SNAPSHOT-INVENTORY.md`: carried into the handover as-is. It is the
  document that tells whoever comes next where this system hides state, and it
  took eight incidents to learn.
- `DEFERRED.md`: everything not built, with the reasoning. English content, reader
  accounts, entity-aware dedupe, Meilisearch, Saudi regulator coverage, the 2.2 AA
  target-size finding, the homepage LCP, the 72-article floor.
- Final `STATUS.md` in the same shape as the last one
- Full decay check, green
- Tag the release
---

# Delivered — Finish Intelligence (was Day 1-2; slot freed)

Close it at a defined line rather than leaving it open.

- `create-draft-from-item` — pre-fills category, topics, entity mentions, source
  record. **Never** headline, body or summary from the publisher's prose.
- `masar:reclassify` with dry-run, as ruled
- Decay check against a **restored-from-backup** database, not a fresh seed
- The stale-snapshot inventory: everything with a TTL, memoised per request, or
  denormalised. You found the cache; I want the list, not the next incident.

Entity-aware dedupe stays deferred and documented.

---

# Working rules for these ten days

Report at the end of each day: what shipped, what slipped, what you'd cut. Short.

If a day's work won't fit, **tell me and cut scope rather than shipping something
half-wired**. A feature that looks built and isn't is the failure mode this project
has hit eight times, and the last ten days are exactly when it happens again.

Do not start anything on Day 9 or 10 that can't finish.

If you think this order is wrong, say so now rather than on day six. You have been
right about sequencing before.
