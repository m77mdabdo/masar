# MASAR — localization audit: every public page and every record in six editions

Date: 2026-10-29. Tested commit: `4ee8c09`.

## Status in one paragraph

- **Done and committed:** the public site serves every page and every published record in all six editions. That covers interface text and database content: stories, citations, sponsors, bylines, entities, opportunities, image alt text and credits, market labels, SEO and OG metadata, and JSON-LD.
- **Verified:**
  - **Leak crawl:** 825 pages in the five non-Arabic editions. No Arabic outside the «مسار» lockup and the language switcher, in either the body or the `<head>`.
  - **Browser audit:** 138 pages at 17 viewports, 2,346 checks, all clean.
  - **Suite:** 1,128 passed.
  - **Decay check:** green.
  - **Fresh seed:** on a scratch database, it comes out 83 of 83 in every edition.
- **Pushed** to `origin/main` (see Git).
- **Not deployed:** there is still no verified server or domain.
- **Not done, and not claimed:**
  - **Native review:** no translation has been reviewed by a native speaker. All non-Arabic text, interface and content, is machine-assisted and published on the owner's authorisation, recorded as such.
  - **New stories:** anything written in Arabic after today has no translation until a person writes one.

## Publication approval and native review — two separate states

- **Stories:** every translated story is `translation_status = owner_authorized`, `translation_origin = machine-assisted`, with no reviewer recorded.
- **The publish gate:**
  - It admits `owner_authorized` only while `MASAR_TRANSLATION_OWNER_AUTHORIZED` is on (default true). Switched off, the gate accepts reviewed translations only.
  - A test refuses the owner-authorised row with the switch off.
- **Interface publication approval** was re-recorded against the final file fingerprints:
  - all 60 lang files, by "Mohamed Abdo (project owner)", basis the 2026-10-29 directive;
  - `fr/site.php` again after its label changed.
- **Linguistic review:** 0 of 531 strings reviewed in every edition, unchanged. No review was fabricated.
- **Editorial standards page:** says exactly this. Originals are written by people, the other editions are machine-assisted and not yet reviewed by native speakers, and the Arabic text is the reference. It shows the "reviewed" wording only when every interface file is approved and no owner-authorised story is published in that edition.

## Coverage — records and strings

| | en | fr | hi | ur-pk | zh-hans |
|---|---|---|---|---|---|
| Interface strings (9 groups + JSON) | 531/531 | 531/531 | 531/531 | 531/531 | 531/531 |
| Published stories | 83/83 | 83/83 | 83/83 | 83/83 | 83/83 |
| Entity and taxonomy records (companies, people, topics, sections, industries, countries, markets) | 71/71 | 71/71 | 71/71 | 71/71 | 71/71 |
| Opportunities | 19/19 | 19/19 | 19/19 | 19/19 | 19/19 |
| Image alt text and credits | complete | complete | complete | complete | complete |
| Bylines, market labels, sponsors | complete | complete | complete | complete | complete |
| Content dictionary strings | 742 | 742 | 742 | 742 | 742 |
| **Missing** | **0** | **0** | **0** | **0** | **0** |

Completeness is 100% of records and strings in every edition. Source: `php artisan masar:translations:coverage`, which exits non-zero on any gap, plus `translation_reviews`. Arabic is the source edition.

**Allowed exceptions** (left as-is by design):
- the MASAR brand and the «مسار» lockup;
- the native language names in the switcher;
- URLs, emails, tickers and slugs (slugs are transliterated Latin, the same in every edition).

**DB coverage held for any seed, not only this one.** The demo stories are assembled at random from fixed pools, and the first dictionaries matched only the draw on dev. A fresh seed on scratch came out **38 of 83**.
- **The fix:** the whole closure the factories can generate is now translated. That is every pool string, all 150 subject × predicate headlines and every riyal value from 1 to 40.
- **The check:** re-seeding scratch gave 83 of 83 in every edition.
- **The guard:** a test fails if any string the factories can produce lacks a translation in any edition.

## Missing-translation detection (tests that fail on leaks)

- **`ContentTranslationsTest`:**
  - **Publication path:** a complete dictionary publishes as `owner_authorized`; an incomplete one publishes nothing and reports the gap; no existing translation is ever overwritten.
  - **Leak checks:** the English story page and its category listing must carry no Arabic outside lang-marked elements. This includes a sponsored story with an Arabic sponsor and fact-checker, and JSON-LD. A detector self-test proves it catches an untranslated byline.
  - **Mechanisms:** a byline read from a column-limited load throws; the factory closure is fully translated.
- **Existing tests:**
  - `TranslationParityTest`: every key, the same placeholders, each edition in its own script.
  - `NoHardcodedInterfaceTextTest`: no Arabic in public templates.
  - The ruling on editorial-standards wording.

## Leak crawl — defects found and fixed

All 825 pages in five editions: every published story, section, topic, company, person, author and opportunity, plus the static pages. Found on the first pass:

| leak | where | cause | fix |
|---|---|---|---|
| «مجموعة الأفق القابضة», «شركة التمكين للخدمات» | sponsor label and JSON-LD on sponsored stories (all article pages via rails) | `sponsor_name` was not a translatable segment | made it one; dictionaries for both sponsors; seeder fills fields still identical to the Arabic source |
| «مدقق معلومات», «رئيس التحرير» | source list's fact-checker | template read `->name` | `display_name` |
| «كاتب» | every card byline on listings | `author:id,name` eager loads dropped `name_translations`; fallback printed Arabic silently | select the column in the three public queries; `display_name` now throws on a column-limited load (a mutant 500s) |
| «المؤشر العام التجريبي» | chart `aria-label` on the homepage | label not passed through `MarketText` | passed through |

Re-crawl: **0 leaks** in body text, alt, aria-label, title and placeholder in 825 pages. **0 Arabic** in `<head>` (titles, meta, OG) or JSON-LD across 133 pages × 4 non-RTL editions; the head crawl excluded Urdu, whose script is Arabic. In Urdu, no element is marked `lang="ar"` outside the lockup and switcher. The one 404 per edition is an opportunity that is closed in every edition, Arabic included.

## SEO

- **`<html lang/dir>`:** correct on 2,346 of 2,346 checks. `ar` and `ur-PK` are RTL; `en`, `fr`, `hi` and `zh-Hans` are LTR.
- **Canonical and hreflang:**
  - Each story lists its published translations as reciprocal alternates.
  - Structural pages list the same route in each edition.
  - A missing edition is absent, never pointed at a front page.
- **Sitemap:**
  - It lists only published rows, so drafts and incomplete translations are excluded. An incomplete story is never published: the seeder skips it and the gate refuses it.
  - Reciprocal hreflang sets are tested.
  - It is hidden in demo mode on purpose, as `robots.txt` disallows all.
- **Switcher:** keeps the page, the story and the query string. `/en/search?q=aramco&page=2` → `/fr/search?page=2&q=aramco` (Symfony orders the parameters). New test.

## Responsive and typography audit

Playwright, every route type in every edition:
- **Routes:** home, section, article, topic, company, person, author, search, markets, video, opportunities, opportunity, newsletter, about, contact, team, advertise, corrections, standards, privacy, terms, cookies, and 404.
- **Viewports (17):** 320, 360, 375, 390, 414, 480, 576, 768, 820, 1024, 1280, 1440, 1920 and 2560, plus landscape 667×375, 844×390 and 1024×768.
- **Checks:**
  - horizontal scroll and elements escaping the viewport;
  - raw translation keys;
  - broken images in view;
  - console and page errors;
  - lang/dir;
  - unmarked Arabic-script text.

| run | checks | h-scroll | escaping | raw keys | broken images | errors (non-404) | unmarked Arabic | lang/dir |
|---|---|---|---|---|---|---|---|---|
| first | 2,346 | 3 | 3 | 0 | 0 | 0 | 0 | 0 wrong |
| final | 2,346 | **0** | **0** | 0 | 0 | 0 | 0 | 0 wrong |

**Defects fixed:**
- **French topic, company and person pages at 320px scrolled sideways by 4–11px.** A long transliterated name in the "appears with" list is a flex item that cannot shrink, so it widened the grid column. Fixed with `min-w-0` on the column and the link.
- **Topbar newsletter button:**
  - In French it wrapped to two lines ("Lettre d'information"); the label is now "Newsletter", common French usage.
  - In Urdu and Chinese at 320px it was squeezed to 32px tall; it now has `shrink-0 whitespace-nowrap`.
  - Measured afterwards: 24px tall, no overflow, in all six editions at 320 and 375.

**Screenshots:** 276 captures (375 and 1440, every route, every edition), written to the session scratchpad, not the repository. Inspected by eye:
- **Hindi article 375:** Devanagari, the sponsor and the fact-check all translated.
- **Urdu article 1440 and home 375:** Nastaliq, RTL, mirrored layout.
- **Chinese home 1440 and opportunity 375:** Western digits.
- **French company 375 and article 1440.**
- **Arabic home 375:** unchanged.

The design, CSP, upload sanitisation, legal pages and backups were not changed by this pass.

## Admin

Still Arabic, by ruling. The articles list has a new column, «النسخ المنشورة» (X/6 editions published, counting published rows only; tested). Each translation's status shows on the article: owner-authorised, needs review, reviewed or outdated. Creating and editing a translation goes through the existing draft → review → gate workflow, unchanged.

## Data operations on dev

Before the import, a verified backup: `masar-20261029T123022Z.sql.gz`, 56 tables, 5,043 rows. Then:
1. the content import;
2. `masar:recount`;
3. `cache:clear`.

**Incident:** a second, smaller run (sponsor names on 10 translated rows) started after a backup attempt had failed, because the commands were joined with `;`. That backup failed because `deploy/backup.sh` defaults to the production path. The 12:30 backup covers the state before that run. A verified backup was taken right after it: `masar-20261029T123614Z.sql.gz`. No production system exists or was touched. `migrate:fresh` ran only on scratch databases, and both were dropped afterwards.

## Tests and decay

- **`php artisan test`:** `Tests: 1128 passed (4298 assertions) · Duration: 92.29s`.
- **Migration:** `2026_10_29_140000_add_name_translations_to_users_table` migrates, rolls back and re-migrates cleanly (`masar_test`).
- **`php artisan masar:decay --with-seed`:**

| check | state | detail |
|---|---|---|
| suite | MANUAL | recorded above |
| queue | ok | depth 0 · failed 0 · worker running |
| migrate:fresh --seed | ok | scratch db · 9843 rows · 278 published articles* |
| flow: published article is served | ok | #198 renders with its headline |
| flow: homepage links published articles | ok | 40 of the latest 50 published articles linked from /ar |
| flow: navigation renders in the masthead | ok | 14 links in `<header>`, 23 menu items in db |
| flow: homepage sections have distinct positions | ok | 18 distinct positions for 18 sections |
| flow: newsletter form posts to the subscribe route | ok | rendered form POSTs to web.newsletter.subscribe |
| flow: source polling succeeds | SKIP | polling disabled — 7 active sources, none polled |
| flow: market ticker renders | ok | rendered |
| flow: configured redirects answer | SKIP | 0 redirects configured |
| public forms | ok | 6 forms, all addressed |

\*278 is the run that exposed the seed gap. The decay check reports it as ok because it checks that the seed runs, not translation coverage. After the closure fix, re-seeding scratch gives 83 of 83 in every edition, which is 498 published rows.

## Limitations

- **Native review:** none. Proper nouns were transliterated by the same machine-assisted pass and want a person first, because a misspelt name is a factual error: fictional companies, demo people, sponsors.
  - Chinese keeps Western digits with Chinese units («40 十亿里亚尔»); that is understandable but not idiomatic («400亿»).
  - A fictional company «شركة المسار الصناعية» is rendered "Al Masar Industrial Company" and similar, never as the brand.
- **No translation pipeline:** the dictionaries are a one-time import. The provider seam still has only the null driver, by ruling.
- **No admin form for `market.translations`:** a new market label renders marked `lang="ar"` in the other editions until the map is extended.
- **System fonts:** Nastaliq, Devanagari and Han use the reader's system face. Windows without Urdu Typesetting falls back to naskh.
- **Admin labels** remain Arabic, by ruling.
- **No production measurement exists.** Every figure here is from dev on 127.0.0.1:8000.

## Git

Commits on top of `bcc7d92`:
- `6f8e87c` feat(translation): publish machine-assisted editions on the owner's authority
- `f4336b2` feat(i18n): render bylines, image text and market labels in the page's edition
- `72df7cf` feat(seed): import every published story and entity in five editions
- `59f4c46` feat(admin): show how many editions carry each story
- `40bf9db` fix(i18n): keep the search and filters when switching edition
- `4d8bd7c` fix(layout): stop sideways scroll and wrapped buttons at 320px
- `c36d819` docs(pages): state that the other editions are machine-assisted and unreviewed
- `4ee8c09` docs: record the owner-authorised publication ruling and content coverage — **the tested commit**
- this report

Push target: `origin/main` (`https://github.com/m77mdabdo/masar.git`). The tested code is `4ee8c09`; the report commit adds only this file.

## Deployment — not attempted

As directed: no verified production server or domain exists. The missing information is unchanged from the release report below: host, SSH user and port, domain and Cloudflare, server state, and whether there is production data to preserve.

---

# MASAR — release report: publication approval, security completion, legal pages

Date: 2026-10-29.

## Status in one paragraph

- **Done and committed:**
  - all six editions public and owner-approved for publication (no native
    review is recorded, because none happened);
  - the Content-Security-Policy and upload re-encoding;
  - privacy, terms and cookie pages in six languages;
  - the 768px tower clipping fixed.
- **Tested:** the suite passes (1,119), the browser matrix passes (1,932 of
  1,932), migrations and a backup were verified on scratch databases, and
  decay is green.
- **Pushed** to `origin/main`.
- **Not deployed.** No production target exists to verify (see Deployment).
  No production database was touched, and none was backed up, because none
  can be reached.

## Translation publication and review

| edition | public | publication | native review | strings | pending review |
|---|---|---|---|---|---|
| Arabic `ar` | yes | owner approved | source text | 530 | — |
| English `en` | yes | owner approved | pending | 530 | 530 |
| French `fr` | yes | owner approved | pending | 530 | 530 |
| Hindi `hi` | yes | owner approved | pending | 530 | 530 |
| Urdu `ur-pk` | yes | owner approved | pending | 530 | 530 |
| Chinese `zh-hans` | yes | owner approved | pending | 530 | 530 |

- **Owner approval** is a separate record (`publication_status`, approver,
  basis, hash, time), written by `masar:translations:authorize-publication`.
  It was recorded on the dev database as "Mohamed Abdo (project owner)", on
  the basis of your directive.
- **It never writes the review fields.** The public notice therefore still
  says, correctly, that the interface awaits native review.
- **A changed file voids both** its approval and its review.
- **Production:** run the same command there after deploy (it's in the runbook).

## Security

| Item | Result |
|---|---|
| CSP (public) | Per-response nonce on every executable script, with no `'unsafe-inline'` for scripts or style elements. `object-src 'none'`; base, form and frame-ancestors restricted to self; media from the media host only; frames from youtube-nocookie only; upgrade-insecure-requests in production. A browser walk of 1,932 checks found 0 violations, and the carousel, switcher, mobile menu and newsletter form all work under it. `'unsafe-eval'` remains, because Alpine's standard build needs it. The admin is not covered; it is outside the `web` group. |
| Uploads | `SafeImage` on all 7 upload fields: magic-byte check (JPEG, PNG, WebP), size and dimension limits, a GD decode, then re-encoding in place. A PHP file renamed `.jpg`, SVG, GIF, PE and ELF files are refused. A payload appended to a real JPEG is stripped. AVIF was dropped. A test fails if an upload field is added without the guard. |
| Cloudflare and rate limits | `TRUSTED_PROXIES` from env. Proven: behind a trusted proxy, reader A's 31st search is refused while reader B still gets through. The origin-firewall procedure is in the runbook. |
| HTTPS, cookies, sessions | Forced HTTPS and secure session cookies in production, both tested. CSRF is on the web group. Login locks on the sixth attempt; password reset sends one email for five requests. |
| config:cache | **Kept off.** It would null `env('TRUSTED_PROXIES')` in `bootstrap/app.php` and drop Cloudflare trust. The reasoning is in the runbook. |

## Legal pages

`/{edition}/privacy`, `/terms` and `/cookies` are live in all six editions and
linked from the footer.
- **What they state:** only behaviour the code has:
  - the visitor cookie and its counting window;
  - what a subscription stores;
  - momentary IP use for rate limits;
  - 404 logging without IPs;
  - no stored searches;
  - no third-party analytics, cookies or ad networks;
  - YouTube only after a click.
- **The cookie table** is generated from the code that sets each cookie, and a
  test holds it equal to the cookies a real response sets: `masar-session`,
  `XSRF-TOKEN`, `masar_edition`, `masar_vid`.
- **Not stated, for you to confirm before relying on them legally:**
  - the operator's legal name, registration and address;
  - a privacy contact (the contact settings are empty);
  - governing law and jurisdiction, for example PDPL;
  - retention periods;
  - the hosting and email providers;
  - whether the measurement cookie needs consent (there is no banner);
  - any age statement.

## Frontend

- **Tower at 768:** an image may now carry `focal_md` for the 768–1023 stage.
  The towers photograph uses 65% there and 56% on phones. Measured from
  pixels, the tower's body ends at 0.878 of the frame; the visible edge at the
  zoom start is now 0.883.
- **Contrast:** re-measured across the motion; the floor is 4.62, above 4.58.
- **First question:** layout unchanged, as you directed.

## Tests

| Check | Result |
|---|---|
| `php artisan test` | **1119 passed (4264 assertions)** — previously 1075 |
| Responsive and i18n browser matrix | **1,932 / 1,932** (23 pages × 6 editions × 14 viewports) — previously 1,680 |
| CSP violations in the browser | 0 |
| Migrations, scratch database | all 18 new migrations rolled back and re-applied; schema identical |
| Backup and restore, scratch database | 56 tables, 5,042 rows, all match the manifest |
| `masar:decay` | no decay |

## Git

Release commits:
- `7d09f45` — CSP and owner publication approval
- `77d4697` — uploads
- `c4d6b7a` — legal pages
- `efb1d09` — tower at 768
- `0d61a82` — Cloudflare and config decisions
- the release docs commit

Pushed to `https://github.com/m77mdabdo/masar.git`, branch `main`. The tested
commit is the pushed commit.

## Deployment — BLOCKED, production unchanged

**No production target can be verified, so I stopped before production as
directed.** The repository names no server: `deploy/nginx/masar.conf` still
has `server_name _` "replaced at domain cutover". Nothing names a host, SSH
user, domain or deploy key for MASAR. The SSH keys on this machine can't be
identified as MASAR's, and I didn't try them.

To deploy, I need:
1. **The server:** hostname or IP, SSH user and port. Authenticate
   interactively or with a key; never paste a password in chat.
2. **The domain**, and whether Cloudflare fronts it.
3. **Server state:** is it provisioned (`deploy/provision.sh`) with a `.env`
   from `deploy/env.production.example`, or new?
4. **The data:** whether it holds a production database that must be backed
   up and preserved first, or is fresh.

From there it is the runbook:
1. `deploy/backup.sh` and `deploy/verify-backup.sh`.
2. `deploy/deploy.sh <ref>`, which runs `migrate --force` and never
   `migrate:fresh`.
3. Record the publication approval.
4. Post-deploy checks of all six editions, legal pages, headers, logs and
   workers.

---

# MASAR — task report: six public editions, review register, commitments, operations

Date: 2026-10-29. Commits `354288c` … `a4abf0d` (7), on top of the 12 from
2026-10-28. Not pushed, not deployed.

**Technical readiness is not the same as production deployment or linguistic
approval.**
- All six public editions are enabled and pass every executed check.
- Nothing is deployed.
- No interface translation has been reviewed by a person. All 2,370
  non-Arabic strings are recorded as `pending_review`.

## Language status

| edition | URL | direction | public | strings | untranslated | reviewed | pending review |
|---|---|---|---|---|---|---|---|
| Arabic | `/ar` | RTL | yes | 474 (source) | — | — | — |
| English | `/en` | LTR | yes | 474 | 0 | 0 | 474 |
| French | `/fr` | LTR | yes | 474 | 0 | 0 | 474 |
| Hindi | `/hi` | LTR | yes | 474 | 0 | 0 | 474 |
| Urdu | `/ur-pk` | RTL | yes | 474 | 0 | 0 | 474 |
| Chinese | `/zh-hans` | LTR | yes | 474 | 0 | 0 | 474 |

## What changed

| Area | Result |
|---|---|
| **R11 — review register** | A `translation_reviews` row per edition and file, holding content hash, string counts and status (`source` / `pending_review` / `approved`). `masar:translations:sync` never approves. `masar:translations:approve <locale> <file> --reviewer=<email>` is the only path to approved; it needs a real account with `translation.review` and the hash on disk. A changed file falls back to pending, with `invalidated_at` recorded. The deploy syncs the register. |
| **Activation** | `MASAR_LOCALES_ENABLED` defaults to all six. Three separate controls: public availability, interface review, editorial publication. An edition is never switched off for having no stories. |
| **R12 — commitments** | Every claim on the public pages was checked against the code. Ten discrepancies were fixed (matrix below). |
| **Settings** | The brand is MASAR in every edition. Tagline, descriptor and a new default SEO description are per edition, with one field per language in Settings; saving one language cannot overwrite another. |
| **Article workflow** | Originals can be created in all six editions through the form (tested). Translation drafts, review by someone other than the translator, the gate and the outdated hash are unchanged. |
| **Focal points** | The towers photograph is cropped at `56% 40%` and the meeting-room photograph at `40% 40%`, stored on their media records (8 and 9 rows) by a reversible migration and read by the big story and the article hero. The right-hand tower stays whole at 768 at rest but is still clipped at the edge at the 1.04 zoom start. |
| **Ops** | Backups record exact per-table row counts; `verify-backup.sh` proves a restore. Trusted proxies come from env, HTTPS is forced in production, and the session cookie is secure by default in production. Adds `deploy/env.production.example` and `deploy/RUNBOOK.md`. |

## R12 — commitments matrix

All six editions render the same sentence from the same key. "Test" names the
test that now holds the claim to its mechanism.

| Claim (page) | Implementation | Result before | Corrective action | Test |
|---|---|---|---|---|
| Every story passes research → writing → fact-check → review → publish; none skipped (team) | `ArticleStatus::allowedTransitions` | **False:** research can be skipped | Now names the enforced order: writing → fact-check → editor review → publication | `CommitmentsTest`: Published unreachable without any one of the three |
| Every story answers all four questions (about, standards) | Gate requires `why_it_matters` only | **Overstated** | Says only "why it matters" is enforced; the other three are a commitment; opinion pieces and success stories excepted | `PublishGateTest` |
| Each photo credited on its story page (footer) | Inline blocks only; hero credit never rendered | **False for the hero** | Hero credit now rendered | `CommitmentsTest` |
| One email a week; unsubscribe link in every email (newsletter, confirmed page, confirmation mail) | Nothing sends a newsletter | **False** | "Has not started yet; every issue will carry an unsubscribe link" | — (no mechanism yet; DEFERRED) |
| Story shows its last-update date (team) | Byline shows it only after an update past publication | **Overstated** | "…if it was changed after publication" | existing byline logic |
| Experts are real people who agreed (team) | Demo people are fictional | **False in demo** | Commitment wording, plus a demo-only sentence saying they are fictional | `CommitmentsTest` |
| Team/expert photos are real photos of real people (footer) | Stock portraits of strangers | **Misleading** | Demo-only: "stock photographs, not the people named" | `CommitmentsTest` |
| Readers are business leaders… (band, advertise) | No readership data | **Unverifiable** | "Written for…" | — |
| Privacy / Terms / Cookies (footer links) | All three went to /about | **False** | Links removed | `CommitmentsTest` |
| Sponsored label «محتوى مدعوم» (advertise) | Renders «محتوى مدفوع» | Fixed 2026-10-28 | Takes the component's own label | `CommitmentsTest` |
| No generated text; written by a person (standards) | Null provider only | True | Narrowed to stories; "written or translated" | `TranslationWorkflowTest` guard |
| Translation reviewed by someone other than the translator before publishing (new) | Gate `require_translation_review`, action check | True | New section | `TranslationWorkflowTest` |
| Interface pending native review (new, non-Arabic editions) | Read from `translation_reviews` at request time | True by construction | New sentence; switches when every file is approved | `CommitmentsTest` |
| Fact-checker ≠ writer (standards) | `TransitionArticleStatus` | True | — | `FactCheckIndependenceTest` |
| Source with a link required (standards, team) | Gate `require_source` (URL filled) | True | — | `PublishGateTest` |
| Sponsored needs sponsor name; label can't be removed (advertise, standards) | Gate, `DisclosureCannotBeWithdrawn` | True | — | `DisclosureIrreversibleTest` |
| Disclosed in page data for search engines (standards) | NewsArticle JSON-LD `sponsor` | True | — | `SchemaTest` |
| Status changes in an audit log with a name (standards) | `RecordStatusAudit` → `causedBy` | True | — | existing |
| Only editorial roles publish (advertise, team) | `article.publish` permission | True | — | `ArticlePolicyTest` |
| Corrections, independence, what we refuse | Phrased as "we", not as a mechanism | Commitments | — | — |

## Responsive matrix (executed, real browser)

- **Coverage:** 20 pages in 6 editions at 14 viewports: 320, 375, 390, 430,
  768, 820, 1024, 1280, 1440, 1920 and 2560, plus landscape 667×375, 844×390
  and 1024×768. That makes **1,680 checks**, run against the live :8000 server
  with all six editions on.
- **Checks:** horizontal scroll a reader can perform; elements escaping the
  viewport; raw translation keys; broken in-view images; console and page
  errors; `<html lang/dir>`; Arabic-script text left unmarked in
  other-script editions.
- **Result: 0 failures in every edition (280 checks each), 0 escaping
  elements, 0 unmarked foreign text.**
- The article and opportunity pages return 404 outside Arabic. Those records
  exist only in Arabic; this is the fallback policy working, not a defect.
- Screenshots at 375 and 1440 for every page and edition are in the
  scratchpad's `i18n-audit/shots/`. I viewed a sample, not all 240.

## Hero fold (Arabic; banner on and off measured identical)

Details are in CLAUDE.md §2.

| device | first question | result |
|---|---|---|
| 375×667 | 620–690, fold 667 | **PASS** (starts above the fold; last 23px below) |
| 390×844 | 594–663 | **PASS** (fully visible) |
| 430×932 / 360×800 / 375×812 | fully visible | **PASS** |
| 768×1024, 1024×768, 1280×800, 1440×900, 1920×1080 | fully visible | **PASS** |
| 1440×788 (real window) | 310–379; CTA 20 above the fold, controls 12 above | **PASS** |
| 844×390 landscape | 203 below the fold | no rule set; reported |

- Mobile ticker: 122px on phones, not 150.
- The standalone error page's 78px bar now matches the live 54px masthead.
  The translation work had changed only its `lang`/`dir` and copy.
- Moving the question above the CTA: measured at 94px earlier on 375×667.
  Not implemented, pending your approval.

## Database

- **Scratch database `masar_migtest`:** `migrate:fresh --seed` gave 56 tables
  and 4,929 rows. Rolling back all 16 new migrations worked: `articles.locale`
  back to length 2, new columns and table gone. Re-applying all 16 gave a
  **schema identical** to the fresh migration and **row counts identical in
  every table**. The scratch database was dropped afterwards.
- **One designed refusal:** the twelve `widen_locale_*` migrations refuse to
  roll back while a row holds `ur-pk` or `zh-hans`, because narrowing would
  truncate those rows. It is documented in the runbook.
- **Dev database:** gained the two new migrations; `RoleSeeder` was re-run
  (adds `translation.review`).

## Backup and restore

- `deploy/backup.sh` was run against the dev database, with media skipped:
  the local media disk is 3.1 GB, and production media live on R2.
- `deploy/verify-backup.sh` restored the dump into `masar_restore_check`:
  **56 tables, 5,039 rows, all match the manifest.**
- It refuses the application's own database by name.
- A tampered manifest is caught: `articles: manifest 999999, restored 108`.

## Security and production configuration

- **Fixed:**
  - no trusted proxies (every reader would share one rate-limit bucket behind
    Cloudflare);
  - no forced HTTPS in production;
  - the session cookie was not marked secure in production.
- **Verified by test:**
  - login lockout on the sixth attempt;
  - password reset: five requests send one email;
  - no proxy trusted by default;
  - HTTPS URLs and secure cookies in production;
  - every `{!! !!}` in a public view escapes its dynamic part.
- **Inspected and unchanged:**
  - CSRF (the web group);
  - search and newsletter limiters;
  - nginx headers (nosniff, frame, referrer, permissions);
  - HSTS ready but commented out until cutover;
  - `deploy.sh` refuses `APP_DEBUG` anything but false.
- **Open:**
  - no Content-Security-Policy;
  - uploads are MIME-validated but not re-encoded;
  - `config:cache` is deliberately absent from the deploy. You asked for it;
    the project's earlier decision and its test say no. I kept it and am
    raising it.

All three are in DEFERRED.md.

## Tests

- `php artisan test`: **1075 passed (4110 assertions)**, run fresh at the end.
- `masar:decay`: no decay (queue drained, worker running; flows and 6 forms
  ok).
- New tests cover:
  - R11: register, no false approval, invalidation, publication separate;
  - six-language activation;
  - commitments;
  - per-edition settings, including the settings page itself;
  - creating originals in five languages;
  - focal points and their rollback;
  - production config;
  - the reset throttle;
  - backup verification in the deploy scripts.

## Acceptance

| Criterion | Status |
|---|---|
| All six public interfaces enabled | **PASS** |
| All six routes load | **PASS** (tests and 1,680 checks) |
| Language switching works | **PASS** |
| RTL and LTR correct | **PASS** |
| Interface keys complete | **PASS** (0 untranslated; parity test) |
| Review status accurately recorded | **PASS** (all pending; no approval recorded) |
| Editorial safeguards intact | **PASS** |
| Localized settings independent | **PASS** |
| SEO references valid; no hreflang to drafts | **PASS** |
| Responsive matrix passes | **PASS** (1,680/1,680) |
| Migrations forward and back | **PASS** (scratch database; one designed refusal documented) |
| Security checks | **PASS** for the scope inspected; CSP and upload re-encoding **DEFERRED** |
| Deployment files present | **PASS** (deploy, backup, restore, verify, Supervisor with Horizon and scheduler, env example, runbook) |
| Backup restoration tested | **PASS** |
| Core functionality operational | **PASS** |
| Admin panel custom labels | **DEFERRED** by your ruling (900 + 58) |
| Native linguistic approval | **NOT DONE**; it is yours (`masar:translations:approve`) |
| Production deployment | **NOT DONE**; needs your go-ahead |

---

# MASAR — task report: six editions and article translation

Date: 2026-10-28. Commits `d2c093c` … `ece2fee` (11). Not pushed.

## Read this first

- **The French, Hindi, Urdu and Chinese interface text was written by Claude,
  not by native speakers.** That is consistent with the ruling of no outside
  translation service. It is not consistent with launching those editions
  unread. Have a native speaker review `lang/{fr,hi,ur-pk,zh-hans}/*.php`,
  and especially `pages.php`, before switching an edition on. That file holds
  the editorial-standards and corrections pages, whose sentences are claims
  about what the system enforces.
- **Only Arabic is live.** The other five editions are built and audited but
  switched off (`MASAR_LOCALES_ENABLED`). No story exists in them yet. An
  edition shows only its own stories, so switching one on today gives a site
  whose chrome is translated and whose listings show honest empty states.
- **The admin panel switches language per user, but MASAR's own panel labels
  are still Arabic:** 900 string literals across 54 files, plus 58 template
  runs. This is the largest open item (see FAIL below).
- **The development database changed.** It received 14 additive migrations,
  two new permissions and the new label rows. A dump taken before any of this
  is at
  `/tmp/claude-501/-Applications-XAMPP-xamppfiles-htdocs-Masar/216eef50-3924-41f8-a70e-0d90257d66e5/scratchpad/masar-before-i18n.sql`.
  To roll back: restore it, run `php artisan cache:clear`, then check out
  `e331f3c`.

## Rulings recorded

Both are in CLAUDE.md §2 and §5:
- **Six editions:** `ar · en · fr · hi · ur-pk · zh-hans`.
- **Machine translation produces reviewed drafts, never publications**, and
  no outside service is used.

## What was built

| Area | What exists now |
|---|---|
| Registry | `config('masar.locales')` is read only through `App\Support\Locales`. Each edition has a URL code (`ur-pk`), a BCP 47 tag (`ur-PK`), an `og:locale`, a direction and a script. Twelve `char(2)` locale columns are now `varchar(10)`; rolling them back refuses rather than truncating `ur-pk` to `ur`. |
| Interface text | 461 keys in `lang/{code}/{site,home,article,pages,errors,enums,entity,mail,admin}.php`, plus Laravel's mail strings as JSON. This covers every public template, all 14 enums, error pages, reader mail (sent in the subscriber's own edition), validation messages and the editorial pages. |
| Switcher | A disclosure of plain links in the topbar: each language named in itself, with its own `lang`/`dir` and `aria-current`. Where the page has no version in a language, the link goes to that edition's front page and says so in that language. There is also a no-JavaScript list in the footer. The chosen edition is remembered for `/`. |
| SEO | One resolver (`Alternates`) feeds hreflang, the switcher and the sitemap. For a story, alternates are its published translations only. The sitemap carries reciprocal `xhtml:link` sets. `og:locale` and `og:locale:alternate` are correct per edition. Site name, tagline and title template are per edition. |
| Fallback | No URL serves another edition's article text. A section, topic or company name with no row in an edition falls back to Arabic, wrapped in `<bdi lang="ar" dir="rtl">`. Editor-entered market labels are marked the same way. |
| Typography | `html[data-script]`: brand faces for Arabic and Latin; system faces for Nastaliq, Devanagari and Han (nothing fetched from outside); Nastaliq at 2.4 leading. |
| Translation workflow | A translation is its own article row in the source's group; `(translation_group_id, locale)` is unique. The steps: **Translate into…** (`CreateTranslationDraft`), then `TranslateArticle` runs queued by id (unique per draft, rate limited, three tries), then a person edits, then **Sign off translation**. The sign-off must come from someone other than the translator, enforced in the action as well as the policy. After that it follows the ordinary workflow, and the publish gate refuses any translation that has not been signed off. "Outdated" is computed from a hash of the source text. A reviewed translation is never overwritten. |
| Engine | `TranslationProvider` with the null driver only. `allow_external` is a second switch that keeps text on the server whatever driver is bound. The provider never sees URLs, figures or Latin names (`SegmentGuard`), and any result that loses one, or comes back in the wrong script, is rejected. |
| Admin | `users.locale`, chosen from the user menu. Direction follows Filament's own translation (Urdu stays RTL). Filament's packaged strings are mapped to our codes. |
| Structured labels | `EditionLabelsSeeder` adds labels for sections, topics, menus and homepage titles in five languages. It is additive and idempotent, and never replaces a label a person wrote. |

## Defects found and fixed along the way

- **The article page advertised draft translations as hreflang alternates.**
  Their URLs return 404.
- **hreflang pointed every missing edition at its own front page.** That tells
  a crawler an English home page is the English version of an Arabic story.
- **The advertise page named a label the site does not render.** It said
  sponsored pieces carry «محتوى مدعوم»; the site shows «محتوى مدفوع». The
  sentence now takes the component's own label.
- **The newsletter band overflowed a 320px screen in every edition**,
  including Arabic, by 32px.
- **The Arabic data grid clipped a figure by 9px at 320.**
- **Found by the audit in other languages:**
  - French menu labels ran 402px past a 1280 viewport; navigation now uses
    short labels.
  - Section titles were `nowrap` and pushed the "All" link off a phone.
  - The lockup read "MASAR MASAR".
  - The tagline stacked to four lines outside Arabic script.
  - An older list of edition links duplicated the new switcher in the topbar.

## Responsive and translation audit

Each of 20 pages was loaded in 6 editions and measured at 10 widths (320,
375, 430, 768, 820, 1024, 1280, 1440, 1920, 2560): **1,200 checks**. It ran
on a second `php artisan serve` on :8002 with `MASAR_LOCALES_ENABLED` set for
that process only. `.env` and the :8000 server were not touched.

Each check looked for:
- sideways scroll the reader can actually perform;
- elements escaping the viewport;
- raw translation keys in the text;
- broken images in view;
- console and page errors;
- `<html lang/dir>`;
- Arabic-script text not marked with its language in other-script editions.

**Final run: 0 failures**, and the re-check after the last two fixes found
0 issues. The article and opportunity pages return 404 outside Arabic
because those records exist only in Arabic; that is the fallback policy
working. Screenshots at 375 and 1440 for every page and edition are in
`/tmp/claude-501/-Applications-XAMPP-xamppfiles-htdocs-Masar/216eef50-3924-41f8-a70e-0d90257d66e5/scratchpad/i18n-audit/shots/`.
I looked at a sample: Urdu Nastaliq, Hindi, French at 1440 and English at
1440. I did not inspect all 240.

## Suite and decay

`php artisan test`: **1033 passed (3940 assertions)**. 43 tests are new, plus
datasets, covering:
- parity of every key across six editions, and each edition in its own script;
- no Arabic in public templates, and every template key resolving;
- alternates, the switcher, the edition cookie and the reciprocal sitemap;
- the translation workflow driven through the Filament buttons, including
  protected-token loss, script checks, never overwriting a reviewed
  translation, and the outdated hash;
- the admin locale;
- the labels seeder;
- per-edition settings.

Each new guard was probed before it was trusted: a planted bad key and a
planted Arabic string both failed the template test.

| check | state | detail |
|---|---|---|
| suite | ok | 1033 passed, recorded above |
| queue | ok | depth 0 · failed 0 · worker running |
| migrate:fresh --seed | ok | scratch db · 4930 rows · 83 published articles (`--with-seed` run) |
| flow: published article is served | ok | #198 renders with its headline |
| flow: homepage links published articles | ok | 40 of the latest 50 linked from /ar |
| flow: navigation renders in the masthead | ok | 14 links in `<header>`, 23 menu items in db |
| flow: homepage sections have distinct positions | ok | 18 for 18 |
| flow: newsletter form posts to the subscribe route | ok | POSTs to web.newsletter.subscribe |
| flow: source polling succeeds | SKIP | polling disabled |
| flow: market ticker renders | ok | rendered |
| flow: configured redirects answer | SKIP | none configured |
| public forms | ok | 6 forms, all addressed |

## Acceptance criteria

| # | Criterion | Status |
|---|---|---|
| 1 | Six languages supported | **PASS.** Registry, routing, interface and rendering verified in all six. |
| 2 | Switcher works across the site | **PASS.** Tests and the rendered audit. |
| 3 | Arabic and Urdu RTL | **PASS.** 400 audit checks. |
| 4 | en/fr/hi/zh LTR | **PASS.** 800 audit checks. |
| 5 | Articles can be created in any language | **NOT TESTED.** The form's language select reads the registry and lists all six. Creating one through the form was not exercised. |
| 6 | Automatic translation when a provider is configured | **BLOCKED** by ruling: no provider. The pipeline is proven end to end with a test provider. |
| 7 | Translations stored persistently | **PASS** |
| 8 | Manual translations editable and protected | **PASS** |
| 9 | Failures recoverable | **PASS.** Stored as `failed` with a content-free message, then retried. |
| 10 | Switching preserves article identity | **PASS** |
| 11 | All interface strings internationalised | **FAIL.** The public site is done; MASAR's own admin labels are not (958 runs). |
| 12 | No known hardcoded strings in scope | **FAIL**, for the same reason. Public templates are enforced by test. |
| 13 | Localised SEO | **PASS.** Note: the Organization JSON-LD `url` still points at `/ar` in every edition. |
| 14 | Responsive at all required widths | **PASS.** 1,200 checks, 0 failures. Automated, plus a sample looked at. |
| 15 | No known critical layout defects | **PASS** |
| 16 | No known critical runtime errors | **PASS** |
| 17 | Existing functionality works | **PASS.** Suite and decay. |
| 18 | Automated tests pass | **PASS** |
| 19 | Migrations validated | **PASS.** Forward, back and forward again on dev; fresh seed on scratch. |
| 20 | Deployment documented | **PASS.** Below. |

Not done and not claimed:
- Visual regression diffing against a baseline.
- Submitting a form in a non-Arabic edition end to end.
- Translation of article content. That is editorial work, through the
  workflow above.

## Deployment

1. `php artisan migrate --force`. This adds 14 migrations, all additive; every
   rollback refuses to lose data.
2. `php artisan db:seed --class=RoleSeeder --force`, for the new permissions
   `article.translate` and `article.translation.review`.
3. `php artisan db:seed --class=EditionLabelsSeeder --force`, which adds
   labels and changes nothing anyone wrote.
4. `php artisan cache:clear`. The navigation and settings snapshots must be
   rebuilt.
5. Keep a queue worker running. Translation jobs use the default queue.
6. Environment variables (all optional; defaults shown):

```
MASAR_LOCALES_ENABLED=ar                 # comma list; switch an edition on only after review
MASAR_TRANSLATION_DRIVER=null            # the only driver, by ruling
MASAR_TRANSLATION_ALLOW_EXTERNAL=false   # no article text leaves the server unless true
MASAR_TRANSLATION_AUTO_ON_PUBLISH=false  # open drafts in every live edition on first publication
MASAR_TRANSLATION_AUTO_RETRANSLATE=false # re-run unreviewed, outdated translations on republish
MASAR_TRANSLATION_PER_MINUTE=10          # provider calls per minute, all workers
```

No credentials are required. None were created or committed.

## Decisions I made that were not specified

- **Automatic drafts open on publication, not on creation.** Creation would
  send unfinished, unpublished drafts down the pipeline; the brief forbids
  sending confidential content without a policy.
- **"Outdated" is never stored.** A stored flag is a second copy that goes
  stale. It is shown on the article page only, because computing it needs the
  blocks, and article lists must not load blocks.
- **Navigation labels are short; section names stay full.** The full French
  names measured 402px past 1280.
- **A hreflang set is listed only when an alternate exists.** The sitemap sets
  are reciprocal.
- **The footer's Privacy / Terms / Cookies links still point at the About
  page.** This is pre-existing and was not fixed here.

The gaps that remain are measured and written up in `DEFERRED.md`, under
"Six editions — what is not done yet".

---

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

---

# Round two: category LCP, and one real feed

Date: 2026-09-15.

## Category LCP — a real defect, found and fixed

The category page's LCP element was **lazy-loaded**. Lighthouse's
`lcp-lazy-loaded` audit scored 0, and the phase breakdown was:

| phase | before | after |
|---|---|---|
| TTFB | 454ms | 454ms |
| **Load Delay** | **2521ms** | **748ms** |
| Load Time | 114ms | 132ms |
| Render Delay | 594ms | — |

Load Time was 114ms. The image was never slow; the browser simply did not ask
for it until layout was done. Only `big-story` and the article hero ever passed
`:eager` — which is exactly why the homepage and article pages measured fast and
every listing page did not. `card-article` and `card-overlay` now accept
`:eager`, and the category featured grid and the markets lead pass it for the
first item only.

**I am not claiming a headline LCP improvement, because I cannot substantiate
one.** In the same session, on the same harness, the untouched homepage moved
from 2053ms (the audit) to 3464ms. Every page shifted by roughly the same
amount, so this session's absolute numbers are not comparable with the audit's —
the machine had spinning processes on it earlier and the 15-minute load average
was still 4.97 when I started. What is comparable, because it is a property of
the markup rather than the clock, is `lcp-lazy-loaded`: 0 before, 1 after, on
category and markets.

Three tests lock it in, and I verified they fail when the fix is reverted.

**The remaining ceiling is fonts, and it is shared.** FCP is ~2410ms on all four
pages because they all load the same 10 font files / 268KB before painting. Two
are preloaded; the other eight are pulled from CSS. I checked whether the Latin
subsets were being fetched only to draw Western numerals — they are not: the
wordmark "MASAR" and the photo credits are real Latin text above the fold. And
re-subsetting is not possible here anyway (no source TTFs, no fonttools). So
this stays exactly where §9 already puts it: a lever that costs design.

## The feed

**Picked: Asharq Al-Awsat (`https://aawsat.com/feed`), plus the US Federal
Reserve press releases as a first probe.**

Why two, and why these:

- I started with the **Federal Reserve** (`/feeds/press_all.xml`) because a
  central bank is a regulator, US federal works are public domain so the terms
  question is unambiguous, and the SAR's peg to the dollar means Fed decisions
  transmit directly into Saudi policy. It is a genuine RSS feed with ETag and
  Last-Modified.
- It turned out to be **the wrong instrument for the measurement you asked
  for.** The rules classifier's keyword table is entirely Arabic. Scoring it
  against English regulator prose measures language mismatch, not classifier
  quality — so I added **Asharq Al-Awsat**, a Saudi-owned Arabic paper whose
  robots.txt permits `/feed`, to get a number that means something.

Rejected along the way, with reasons:

| candidate | why not |
|---|---|
| Saudi Press Agency | No RSS at all — every feed path 307s to `/ar` |
| US EIA (`/rss/todayinenergy.xml`) | Returns 200, but **`robots.txt` says `Disallow: /rss`** |
| Saudi Exchange (Tadawul) | `robots.txt` itself returns 403 |
| GASTAT, MoF, Vision 2030, Arab News | No feed autodiscovery, no feed at the conventional paths |
| `arabic.rt.com` | A real Arabic feed, but Russian state media — wrong source for MASAR |

The EIA case is the one worth keeping: a feed that serves perfectly well and
that we are asked not to take.

## robots.txt was not implemented — it is now

**This blocked everything else.** `HttpClient`'s own docblock said politeness
"lives here so no fetcher can forget it", `tasks/STATUS.md` listed robots.txt
honouring among the things "implemented and unit-tested", and there was **no
robots.txt handling anywhere in the codebase.** A bot that identifies itself
honestly and then ignores the one file whose purpose is to say where not to go
is worse than an anonymous one: it is rude under its own name.

`RobotsPolicy` now answers before any request leaves. It follows RFC 9309 on the
parts that matter — most specific User-Agent group wins, longest matching path
wins with Allow breaking ties, `Disallow:` with an empty value allows all, `*`
and `$` wildcards honoured, 404 means no restrictions, and **a 5xx or an
unreachable host means refuse**, because "unreachable" is not "permitted".
Cached six hours. Nine tests, including one asserting the feed URL is never
requested at all when robots refuses.

Two existing fetcher tests broke and were correct to break: a wildcard
`Http::fake` was answering robots.txt with the 304 or 429 under test.

## What was measured, on real items

First poll of each, with the real User-Agent from the first request:

| | Federal Reserve | Asharq Al-Awsat |
|---|---|---|
| items stored | 20 | 50 (feed carried 300; `max_items_per_poll` capped it) |
| health | healthy | healthy |
| poll frequency set | 360 min | 180 min |

**Conditional GET works, proven both ways.** The second poll returned 0 new and
left `last_item_at` untouched while advancing `last_success_at` — a 304 treated
as success, not as a missing feed. Confirmed independently with curl:

    unconditional:        200, 14620 bytes
    with If-None-Match:   304, 0 bytes

### The rules rate

**30% — 15 of 50 real Arabic items classified by rules, 70% unclassified.**

| document_type | items |
|---|---|
| statistic | 5 |
| decision | 3 |
| funding | 2 |
| report | 2 |
| announcement | 2 |
| earnings | 1 |
| other (unclassified) | 35 |

The 30% is honest but needs one qualification: this is a general newspaper feed,
not a business wire. A good part of the unclassified 70% is sport and foreign
politics — items that *should* not classify as a business document type. The
rate against a business-only feed would be higher. The classifications it did
make look right: the top of the importance ranking is monetary policy and Saudi
corporate news, and importance spread 47–69 rather than sitting flat.

Against the Fed feed the rules rate was **0% of 20**, and that number measures
nothing except that the keyword table is Arabic and the feed is English.

### The four-stage dedupe fires on real items — and over-fires

**8 flags across 70 real items. All 8 are false positives. Zero true positives.**

All eight came from the title (SimHash) stage. On the Fed feed, five distinct
enforcement actions against different banks were collapsed into each other
because they share a long boilerplate prefix. On the Arabic feed, three were
worse — a headline about a rare-disease gene was flagged as a duplicate of a
market-listing rule.

Measured distances against the threshold of 16:

| pair | distance |
|---|---|
| genuinely the same story (Aramco Q3, two phrasings) | 9 |
| copper prices vs. sector piece (unrelated) | 14 |
| German wholesale prices vs. revenue mix (unrelated) | 16 |
| rare-disease gene vs. listing rules (unrelated) | 16 |

And across all 1,225 pairs of the 50 real Arabic titles: **min 17, p05 24,
median 31 — zero inside the threshold.**

So the config comment claiming unrelated stories measure 26–33 is wrong against
real data. Real unrelated pairs bottom out at 17, one point above the cutoff,
and cross-corpus pairs reach 14. The threshold has essentially no margin, which
is why it produced eight false positives and no true ones.

**I have not changed it.** The evidence says 10 would keep the genuine
near-duplicate at 9 and exclude everything observed at 14 and above, but that is
one true-positive data point, and `IntelligenceDedupTest` pins the distribution
the config comment describes. This is a calibration decision with editorial
consequences — an over-eager dedupe hides real stories from an editor — and
you have been right to want these put to you rather than split. My
recommendation is 10.

## Decay check

`php artisan test` — **691 passed, 2,259 assertions, 45.09s**, zero skipped.

| check | state | detail |
|---|---|---|
| suite | ok | 691 passed (2,259 assertions) |
| queue | ok | depth 0 · failed 0 · worker running |
| migrate:fresh --seed | SKIP | not run — `--with-seed` (~15 min) |
| flow: publish an article | ok | 72 published |
| flow: article on the homepage | ok | 19 sections |
| flow: navigation renders | ok | 23 menu items |
| flow: homepage order is editable | ok | 19 distinct positions |
| flow: newsletter stores a subscriber | ok | POST route present |
| flow: source poll reaches the inbox | ok | 9 active sources · 92 inbox items |
| flow: redirect becomes a 301 | ok | 0 redirects configured |
| public forms | ok | 5 forms, all addressed |

## Open for you

1. **The dedupe threshold.** Recommendation: 16 to 10, with the distribution
   comment in `config/masar.php` corrected to the measured figures.
2. **`MASAR_USER_AGENT` names `https://masar.sa/bot` and `contact@masar.sa`.**
   Both were sent to two real publishers today. If that domain and mailbox do
   not yet resolve, the contact line is decoration, and the whole point of the
   header is that a publisher can reach a human. Worth settling before the
   poller runs on a schedule.
3. **Two live sources are now in the dev database** (ids 8 and 9), polling at
   360 and 180 minutes. Nothing schedules them yet — they run when
   `masar:check-sources` is invoked.

---

# Round three: threshold, second rules rate, scheduler

Date: 2026-09-15.

## 1. Dedupe threshold — 10, and a correction to my own recommendation

Changed to 10 as ruled. But measuring the true-positive side properly, which I
had not done, contradicts the recommendation I gave you.

**Your 26-33 was not wrong.** It reproduces exactly: on the constructed pairs it
was taken from, same-publisher-different-story still measures 25 and unrelated
still measures 30, and 16 does sit between them. The flaw was the sample —
every pair shared the stem "هيئة السوق المالية", so shared tokens held the
distances high. The gap was an artefact of the corpus, not a property of the
method. I have corrected the config comment to say that rather than implying
the old figures were invented.

Measured against pairs that do **not** share a stem, the classes overlap and
**no threshold separates them**:

| pair | distance | truth |
|---|---|---|
| "عاجل:" prefix added | 5 | same story |
| مؤشر تاسي reworded | 14 | same story |
| البنك المركزي paraphrased as ساما | 20 | same story |
| أرامكو vs سابك, identical sentence otherwise | **7** | **different stories** |
| copper price vs sector piece | 14 | different stories |

A different story scores 7 while a genuine rewrite scores 20. SimHash weights
tokens by frequency; across six or seven tokens the shared domain words dominate
and the one word naming the subject barely moves a bit.

Sweeping every threshold from 6 to 18 against six labelled same-story pairs and
four different-story pairs, the best available is: **10 catches 3 of 6 real
duplicates and wrongly flags 1 of 4 different stories.** 16 caught 5 of 6 but
wrongly flagged 3 of 4. So 10 is the least bad, not a fix.

This is survivable only because the behaviour is flag-never-discard, which I
verified is what `StoreSourceItem` does — the row is created either way and
carries `duplicate_of_id`. A test now asserts nothing is removed.

`DedupTest` pins exact distances instead of the old wide bands (1-16, 17-64),
which were loose enough to pass whatever the normaliser did — that looseness is
why an unchecked calibration survived. Two new tests: one pinning the threshold
at 10, one recording the overlap with the أرامكو/سابك and البنك المركزي/ساما
pairs, so the gap cannot be re-invented.

**The real fix is a different signal for short headlines** — token overlap on
content words, or an entity-aware comparison that weights the subject — not a
better number. Recorded in the config comment.

## 2. The second rules rate

**50% on a business feed, against 30% on the mixed feed.**

Measured on `aawsat.com/feed/economy` — the economy section of a publisher we
had already contacted, chosen deliberately so ruling 3 did not require
introducing ourselves to a new publisher with a contact URL that does not
resolve.

| corpus | items | rules | unclassified |
|---|---|---|---|
| general newspaper feed | 50 | **30%** | 70% |
| economy section | 20 | **50%** | 50% |

By your criterion the keyword table is not the bottleneck: 50% is meaningfully
above 30%. But 20 items is a small sample, and the more useful finding is what
the unclassified half actually is.

**No Saudi regulator publishes RSS.** SPA has no feed, Tadawul's robots.txt
returns 403, CMA redirects, and GASTAT, MoF and Vision 2030 have no feed at any
conventional path. So a true regulator corpus was not available to measure.

That matters, because the taxonomy is built for regulator output. All ten
`DocumentType` cases — decision, regulation, tender, licence, funding,
appointment, earnings, statistic, announcement, report — are institutional
document types. The ten unclassified items are not institutional documents at
all:

- **market moves** (4): "برنت يتجاوز 108 دولارات", "عوائد السندات اليابانية تقفز"
- **forecasts** (2): "«مورغان ستانلي» يتوقع رفع الفائدة الفيدرالية مرتين"
- **executive statements** (3): "«إكسون موبيل»: اضطراب إمدادات الغاز قصير الأمد"
- **policy narrative** (1): "اليابان توازن بين الإنفاق الدفاعي وضغوط المالية العامة"

So 50% is not a keyword-coverage failure. It is the rate you get feeding
newspaper copy into a regulator-document taxonomy. Closing it means deciding
whether the inbox should carry market moves, forecasts and statements as types
at all — a product question, not a tuning one.

## 3. Scheduled polling — stopped

`masar:check-sources` was registered on the scheduler every five minutes. It is
now registered **only** when `masar.intelligence.polling_enabled` is true, which
defaults to false.

Registered conditionally rather than guarded with `->when()`, because
`schedule:list` evaluates neither and a guarded entry still prints as though it
runs. Verified both ways: absent from the list by default, present with the flag
on. A manual run prints that the schedule is off and why.

CLAUDE.md §5 carries the rule verbatim, next to the gate that enforces it.

Three live sources are registered (ids 8, 9, 10) and none is scheduled.

## Decay check

`php artisan test` — **693 passed, 2,243 assertions, 43.99s**.

## Open for you

1. **masar.sa** — the gate opens when `/bot` serves a page and the mailbox works.
2. **Whether the inbox should classify market moves, forecasts and statements.**
   That is what the unclassified half is, and no keyword will fix it.
3. **The title-stage signal.** 10 is the least bad threshold, not a working one.

---

# Round four: taxonomy, and the start of C-F

Date: 2026-09-15.

## 1. Title stage recorded as known-weak

`config/masar.php` now carries the three labelled pairs as evidence, in the
comment beside the threshold, headed so nobody tunes the number expecting it to
help:

    "أرامكو تعلن نتائج الربع الثالث" / "سابك ..."      7   DIFFERENT
    "البنك المركزي يبقي على سعر الفائدة" / "ساما ..."  20   SAME
    "النحاس يتعافى ..." / "حديث عن مرحلة جديدة ..."    14   DIFFERENT

With the replacement named: an entity-aware comparison, where two headlines
naming different companies cannot be duplicates however similar the sentence.
The extractor already exists. Threshold stays 10, behaviour stays flag-never-
discard, and the URL stage carries the load.

## 2. Taxonomy extended — and the table was never the problem

`market_move`, `forecast` and `statement` added, with weights that keep the
ordering honest: a market move is 0.6, below earnings and statistics at 0.7 and
well below a decision at 1.0, because a price move is a transient fact a market
participant usually knows before we do. Forecast and statement sit at 0.4
alongside announcement — an opinion about the future and a piece of positioning
respectively. None of them binds anyone to anything, which is what separates
them from a decision.

Keywords key on movement verbs, hedging verbs and attribution, because that is
what distinguishes these forms in a headline rather than the subject.
`statistic` keeps "ارتفاع بنسبة" — a percentage reported as a figure is a
statistic; a price crossing a level is a market move.

**Re-measured on the same stored items, no refetch:**

| corpus | items | before | after |
|---|---|---|---|
| general newspaper feed | 50 | 30% | **62%** |
| economy section | 20 | 50% | **90%** |

You said if it jumped to 80% we would learn the table was never the problem. It
went to 90% on the business feed. The taxonomy was missing three forms, not the
keywords.

Per item on the business feed: **8 newly typed, 2 improved, 0 regressions.**
The two improvements are both real — "اليوان يتراجع والأسهم الصينية تستقر" was
typed `statistic` and is a market move; "الأسهم الأوروبية تتراجع بضغط من خسائر
البنوك" was typed `earnings` and is also a market move.

One correction on the way: my first comparison showed five regressions and I
nearly reported them. They were an artefact of my own script — it re-classified
the cleaned `IntelligenceItem` fields while the original run had classified the
raw feed fields. Comparing like with like, there are none.

The two items still unclassified on the business feed are narrative rather than
documentary — "اليابان توازن بين الإنفاق الدفاعي وضغوط المالية العامة" and
"قلق في الأسواق بعد دعوات قادة الذكاء الاصطناعي". Unclassified is the right
answer for both.

**114 stored items were re-derived** — classification, evidence and importance —
so the inbox does not show two vintages of taxonomy. 28 changed type. Worth
noting for later: a taxonomy change makes every stored row stale, and there is
no command for that the way `masar:recount` exists for the publish gate.

## 3. No Saudi regulator publishes RSS — recorded

CLAUDE.md §5 now states it as a product fact with the evidence, and that
**coverage of Saudi regulators is a manual editorial function, not an automated
one**, so no later reader assumes the inbox watches them.

## TASK 07 C-F — started

There is no TASK 07 file in the repository. I am working from my own description
in `tasks/STATUS.md` §4, which lists: the triage inbox, the dismissal and
30-day recovery flow, the near-duplicate review surface, and "create draft from
item". The first two were built in round two. **If the real task file says
something different, say so and I will re-scope.**

Built this round: **the near-duplicate review surface**, which the dedupe
measurement made the urgent one. An editor can now see what an item was flagged
against — the matched item's title, the stage that matched, and a link to it,
visible by default rather than behind a column toggle — and clear a wrong flag
with one action. Clearing records who decided and when, and leaves the item in
the queue, because clearing a flag is not triaging the item.

Remaining: **"create draft from item"**, which pre-fills the frame and never the
prose.

## Decay check

`php artisan test` — **695 passed, 2,251 assertions, 43.08s**.

| check | state | detail |
|---|---|---|
| suite | ok | 695 passed (2,251 assertions) |
| queue | ok | depth 0 · failed 0 · worker running |
| flow: source poll reaches the inbox | ok | 10 active sources · 114 inbox items |
| public forms | ok | 5 forms, all addressed |

All other decay lines unchanged and green.

---

# Round five: the missing navigation and ticker

Date: 2026-09-15.

## The cause: one stale cache, not the seeders

It was not the seeders. Every row was present and correct the whole time:

    menus                4, with 23 menu_items, all is_active = true
    labels               السعودية والأسواق, الأعمال والشركات, رؤى وفرص ... all six
    settings             7 rows, including market.as_of = "2026-09-14 18:07"
    MenuTreeQuery        returns 6 items for ('header', 'ar', 'desktop')

What differed was what the application could see:

| | through the app | in the database |
|---|---|---|
| `setting('market.ticker')` | **0 rows** | 736 bytes |
| `setting('market.as_of')` | **NULL** | 2026-09-14 18:07 |
| cached `menu:header:ar:desktop` | **0 items** | query returns 6 |

Both caches held **empty snapshots taken while the database was wiped**, each on
a 24-hour TTL. The homepage was fetched during that window — by my own curl
checks and by the Lighthouse runs — and that is what wrote them.

`php artisan cache:clear` alone restored both. That is the whole fix and it
confirms the diagnosis: nothing was missing, nothing needed re-seeding.

The `as_of` gate was working exactly as designed throughout. It withholds
undated figures, and through the cache the figures were undated. The gate cannot
tell "there are no figures" from "the cache is hiding the figures", and it is not
its job to.

**The hamburger is the same cause.** Its container is
`ms-auto flex ... xl:border-s xl:border-line xl:ps-4` — a vertical hairline whose
purpose is to separate it *from the navigation*. With the nav empty the hairline
sits directly against the three bars and reads as a box drawn round them. It
should be back to three lines now; worth confirming on a hard reload, since a
stale tab can also hold a build hash that no longer exists.

Two things I checked and ruled out: the CSS build was stale (missing
`--color-coral-ink`, which I added after the last build) but contained all three
of the hamburger's utilities — I got that wrong twice by grepping for
Tailwind's escaped selectors without the escapes, and only the class without a
dot in it matched. Rebuilt anyway. And nothing in the intelligence work touches
the layout.

## The deeper problem, which is the real finding

**A fresh install and a restored install were different sites, and every check
we had said they were the same.**

`migrate:fresh --seed` does not touch the cache. Neither did anything else in
the restore. So the restored install served a populated database through an
empty cache, and:

- **695 tests passed** throughout. Tests run on the `array` cache driver, so
  this class of bug is structurally invisible to them.
- **My own decay check passed**, reporting `navigation renders — 23 menu items`.
  It counted rows in `menu_items`. The site had no navigation. A check that asks
  the database whether the page is right is not a check on the page, and I wrote
  it that way.
- Both templates degrade silently by design: `masthead` takes
  `:menu="$headerMenu ?? collect()"`, and the ticker is wrapped in
  `@if ($ticker)`. Neither errors, warns or logs.

### What changed

**1. Seeding now flushes the cache.** `DatabaseSeeder` ends with `Cache::flush()`
and says so on the console. This is what makes a restored install and a fresh
install the same site, and it is the one change that would have prevented the
whole thing.

**2. The decay check reads the rendered page.** `navigation renders in the
masthead` now counts links inside `<header>` and reports both numbers, and a new
`market ticker renders` check distinguishes the gate working from the band being
hidden. Verified by recreating the broken state — empty cached snapshots in
front of a populated database:

    | flow: navigation renders in the masthead | FAIL | 2 links in <header>, 23 menu items in db     |
    | flow: market ticker renders              | FAIL | market.as_of is set but no ticker on the page |

Both green again after the flush.

**3. Three tests assert the rendered chrome** — the masthead carries the nav
labels, the band renders when figures are dated, and the band is correctly
withheld when they are not. The third matters as much as the first: it is what
keeps the gate distinguishable from the failure.

Three mistakes of my own on the way, all caught by checking rather than
assuming: `toContain()` takes needles rather than a failure message, so passing
a message asserted the header contained the message text; `MarketFiguresSeeder`
guards `app()->environment('local')` and is a no-op under `APP_ENV=testing`, so
seeding it in a test seeds nothing; and `Settings` memoises the whole table per
request, so seeding behind a resolved singleton leaves the memo holding the
pre-seed state — the same shape as the bug itself, one scope smaller.

## Decay check

`php artisan test` — **698 passed, 2,260 assertions, 43.36s**.

| check | state | detail |
|---|---|---|
| suite | ok | 698 passed (2,260 assertions) |
| queue | ok | depth 0 · failed 0 · worker running |
| flow: navigation renders in the masthead | ok | 14 links in `<header>`, 23 menu items in db |
| flow: market ticker renders | ok | rendered |
| public forms | ok | 5 forms, all addressed |

All other lines unchanged and green.

---

# Round six: create-draft, the restore proof, and the stale-snapshot register

Date: 2026-09-15.

## create-draft-from-item

`CreateDraftFromIntelligenceItem` opens an article carrying the frame and not
one word of the publisher's writing — **the headline included**. MASAR's
headline is MASAR's writing, so the draft opens with a placeholder that says so
(`مسودة بحاجة إلى عنوان`) rather than a borrowed line an editor might leave in
by accident. The body never travels at all.

The publisher's headline appears in exactly one place: the source citation,
because that is what attribution is. `article_sources` gets the title, the URL,
the publisher and the access date.

What else the frame carries: status `research`, the acting editor as author, the
site's locale rather than the source's (a Fed release in English can inform an
Arabic story), and a placeholder slug — a slug is an editorial decision and this
is not one. `intelligence_items.article_id` links the two and the item moves to
`drafted`.

The draft is deliberately unpublishable: the gate wants a headline, a summary,
`why_it_matters` and a hero, and this supplies none of them. A test asserts the
gate still returns reasons.

Six tests, including one that puts marker strings in `raw_title` and `raw_body`
and asserts neither reaches the article record.

## The restore proof

You asked for the decay check against a restored database rather than a fresh
seed. I ran it twice, because the first run was not a real test.

**First attempt, which proved nothing:** dump, drop, restore, check — green. But
the cache had been warmed from the *correct* data before the restore, so there
was nothing stale to find.

**The real test** reproduces the incident on the restore path:

    1. wipe the database                       articles: 0
    2. load the homepage once                  -> empty snapshot cached
    3. restore from the dump                   articles: 97, menu_items: 23
    4. masar:decay

    | flow: navigation renders in the masthead | FAIL | 2 links in <header>, 23 menu items in db     |
    | flow: market ticker renders              | FAIL | market.as_of is set but no ticker on the page |

**So the answer to "prove they aren't different any more" is: they still are,
and now something tells us.** `Cache::flush()` in `DatabaseSeeder` fixes
`migrate:fresh --seed`. It cannot fix a backup restore, because `mysql < dump.sql`
never runs application code — nothing in Laravel is in the path to flush
anything. No application-level fix exists for that.

What exists instead: the decay check catches it, and now prints the remedy when
a rendering check fails. CLAUDE.md §9 carries the rule — **restore, then
`cache:clear`, then run the decay check.** After the flush, all checks green and
the site whole.

Counter drift after the restore: `masar:recount --dry-run` reports 0 rows out of
date on all four counters.

## The stale-snapshot register

Everything in this system that can hold a view of the world older than the world.
Ordered by how badly it fails and how quietly.

### Proven to have caused an incident

| what | key | TTL | invalidated by |
|---|---|---|---|
| **Settings** (whole table as one array) | `masar.settings.all`, tag `settings` | **86400s** | `Settings::flush()` on write |
| **Navigation** | `menu:{key}:{locale}:{device}`, tag `navigation` | **86400s** | `FlushNavigationCache` on save |

Both hid live content for hours. Both are tagged and flushed correctly *when the
application writes*; neither notices a change made underneath it.

### Same shape, not yet bitten

| what | TTL | risk |
|---|---|---|
| Homepage composition (`homepage`, `articles`) | 900s | 15 minutes of stale front page after an out-of-band change |
| Article page (`articles`) | 3600s | an hour |
| Sitemap (`articles`, `navigation`) | 600s | search engines see an old list |
| **`intelligence:company-names`** | 3600s | **untagged** — `Cache::tags(...)->flush()` does not clear it. A company added or renamed does not affect importance scoring for an hour, and no tag flush will hurry it |
| **`RobotsPolicy`** per origin | **6 hours** | mine, and the one I would fix first: a publisher who tightens `robots.txt` is not honoured for up to six hours. That is a politeness gap facing a third party, not a data gap facing us |

### Memoised per request

**`Settings` is a singleton holding the whole table in `$this->memo`.** Anything
that writes settings outside the request — a seeder, a raw import, another
process — is invisible for the rest of it. This is the bug one scope smaller,
and it cost me a confusing test failure this round: seeding behind a resolved
singleton left the memo holding the pre-seed state.

### Denormalised columns

| column | rebuilt by | gap |
|---|---|---|
| `topics.articles_count` | `masar:recount` | drifts on any out-of-band insert |
| `companies.mentions_count`, `people.mentions_count` | `masar:recount` | same |
| `articles.gate_failures_count` | `masar:recount` | NULL means "never evaluated", already documented in §9 |
| **`articles.views_count`** | **nothing** | buffered in Redis and flushed periodically. A Redis loss silently discards the buffer, and no command rebuilds it from source because there is no source — the buffer *is* the source |

### Outside the application

- **`config:cache`** — not currently generated here (`bootstrap/cache/config.php`
  is absent), but if it is ever run in production, `env()` stops being read and
  a changed `.env` does nothing at all, silently. `packages.php` and
  `services.php` are present and are rebuilt by composer.
- **`public/build`** — gitignored and per-machine, so two machines can serve
  different sites from the same commit. A rebuild deletes the previous hash, so
  any already-open tab 404s on its assets. The 404 log has already recorded this
  happening (`/build/assets/app-CXaz7BH-.js`, 5 hits).
- **Sessions in Redis** — survive a database restore, so a live session can hold
  a user id that no longer exists.
- **Queued jobs in Redis** — survive `migrate:fresh`, which is the 45-failure
  incident from round two. Fixed for `FlushArticleCaches` by removing the model
  from the payload; the pattern applies to every queued job written from here on,
  and CLAUDE.md §3 carries the rule.

### The two I would act on

1. **`RobotsPolicy` at six hours** is the only one facing a third party. A
   publisher's "please stop" should not take six hours to reach us.
2. **`intelligence:company-names` being untagged** makes it invisible to every
   tag flush we have, which is precisely the property that makes a stale cache
   hard to find.

## Decay check

`php artisan test` — **704 passed, 2,278 assertions, 46.43s**.

All decay lines green against the restored database after `cache:clear`.

---

# Day 1-2 — Finish Intelligence

Date: 2026-09-15. Plan reordered as instructed; deployment now Day 8-9.

## Shipped

**`create-draft-from-item` completed.** It existed from the last round but did
not meet the plan's own wording: the plan says it pre-fills "category, topics,
entity mentions, source record" and mine did category and source only.

Entity mentions now carry over, via a new `App\Support\EntityMatcher` — that a
headline names Aramco is a fact about the headline, so it travels; what the
story says about Aramco is writing, so it does not. An entity we have never
heard of stays unknown rather than being guessed at.

**Topics deliberately do not carry over.** An intelligence item holds no topic
signal at all — there is no field to derive one from, and picking a topic from
the source's category would be making an editorial decision inside an action.
Left to the editor and documented in the class. Flagging it because it is a
knowing departure from the plan's wording, not an oversight.

The matcher's cache is **tagged**, unlike `ImportanceScorer`'s private copy of
the same company list, which the stale-snapshot register flags as invisible to
every tag flush we have. The two should collapse once the scorer's measured
output can be re-verified; noted in the class rather than done mid-plan.

**`masar:reclassify` built, with `--dry-run` and `--source=`.** This is the gap
I flagged last round: classification happens once at ingest, so the keyword
table and the taxonomy are frozen into every row, and adding `market_move`,
`forecast` and `statement` left 114 stored items on a taxonomy that had no such
forms. `masar:recount` exists for that shape of staleness on the publish gate;
this is its counterpart. It reads `raw_title`/`raw_summary` — the input the
classifier actually saw — because comparing against the cleaned copy is what
made a clean re-derivation look like five regressions last round. It never
touches review state: an editor's judgement is theirs.

Two things I had wrong and corrected while building it: I wrote a branch for
items whose source row had been purged, and there is no such case —
`source_item_id` is NOT NULL with a cascading key, and retention nulls
`raw_body` while keeping the row. Dead code, and a test asserting an impossible
state. Both replaced with the real scenario: a purged item still has its title
and summary, so it stays classifiable for life.

## One finding, and it belongs in the register

The first dry run reported **0 type changes and 38 of 114 importance scores
changed**. The types were already right — last round's backfill held. The scores
moved because **`importance` is stale by construction**:

    recency = max(0, 1 - age / 48h), computed against now()

It is a time-dependent value written into a column. Every stored score freezes an
item's age at the moment it was ingested, and the recency weight then means
nothing — it varies only by how stale the feed's own listing was, not by how old
the item is when an editor looks at it. A third of the corpus drifted in a day.

So the inbox's "most important first" ordering degrades quietly unless something
re-scores it. Two ways out: run `masar:reclassify` on a schedule, or compute
recency at read time and stop storing it. The second is the right design and is
a change to a measured system, so it is flagged rather than done here.

Register addition:

| what | why it goes stale | rebuilt by |
|---|---|---|
| `intelligence_items.importance` | recency decays against `now()` but the score is stored | `masar:reclassify` — nothing automatic |

## Already done, carried from earlier rounds

- Decay check against a restored-from-backup database — round six. It failed on
  the restore path, which is the finding: `Cache::flush()` in the seeder cannot
  reach a raw SQL import. §9 carries the rule.
- The stale-snapshot register — round six, now with the importance entry above.
- Entity-aware dedupe deferred and documented in `config/masar.php`, with the
  three labelled pairs as evidence.

## Decay check

`php artisan test` — **711 passed, 2,297 assertions, 44.66s**. All decay lines
green.

Tests went 704 to 711: five for `masar:reclassify`, two for entity carry-over.

---

# Day 1-2 — Deployment, the domain-free half (in progress)

Date: 2026-09-15. Order changed: deployment split rather than moved. Intelligence
slot freed and marked delivered.

## The demo-mode switch — built, tested, on

One setting, `site.demo_mode`, driving four surfaces, **defaulting closed**. A
fresh deploy with no settings row is not indexed; someone has to decide to open
it, which is the right direction for a decision that cannot be undone.

| surface | while on | while off |
|---|---|---|
| robots meta | `noindex, nofollow` site-wide | per-page flag only |
| `robots.txt` | `Disallow: /`, no sitemap line | the editable `seo.robots` setting |
| `/sitemap.xml`, `/news-sitemap.xml` | 404 | served |
| reader-facing banner | «نسخة تجريبية …» | absent |

`nofollow` as well as `noindex`, because we do not want a crawler walking from
the banner into generated copy. The sitemap 404s rather than serving an empty
document: handing a crawler the full list of generated articles while robots.txt
says stay out is a contradiction that resolves in the crawler's favour often
enough to matter.

The toggle sits first in the SEO tab, above `robots.txt`, because while it is on
the field below it does nothing. Its helper text says that in Arabic.

## What this uncovered, and it would have shipped

**`public/robots.txt` was a static file shadowing the route.** It came with the
Laravel skeleton and had been winning all along under any server that tries
files before routes — `artisan serve` and nginx `try_files` both do.

So `RobotsController`, the editable `seo.robots` setting and the sitemap
reference **had never been reachable in any real environment**, and my demo-mode
robots.txt would have silently done nothing on the deployed server. The existing
test asserted `/robots.txt` contains a sitemap reference and passed the whole
time, because Laravel's test client dispatches through the kernel and never
touches the filesystem.

Deleted. The route serves now, verified in both demo states. A test asserts the
file does not exist — a filesystem assertion, because no HTTP test in this suite
can see the problem. I swept all 63 static-URI routes against `public/`; this
was the only one.

This is the argument for splitting deployment rather than moving it, made
concrete on the first afternoon: it is a production-only failure that local
testing structurally could not show, and it surfaced because the work started
now rather than on Day 8.

Four existing tests broke and were correct to break — they assume an indexable
site, and the default is now deliberately the other way. They set the switch
explicitly rather than relying on a default that points elsewhere.

## Decay check

`php artisan test` — **718 passed, 2,315 assertions, 44.97s**. All decay lines
green. Live site: `robots.txt` disallows everything, sitemaps 404, banner
showing.

## What I need from you — server spec

**Provider-agnostic. Hetzner, DigitalOcean, Vultr, Linode, OVH all fine.**

| | |
|---|---|
| OS | **Ubuntu 24.04 LTS**, x86_64 |
| Size | **4 vCPU / 8 GB RAM** recommended; 2 vCPU / 4 GB is the floor. MySQL, Redis, PHP-FPM, Horizon workers and GD image conversions share one box |
| Disk | **40 GB SSD** is plenty **if** media goes to R2. Without R2, 80 GB — the local media disk is 2.1 GB at 206 demo images and grows with every conversion |
| Network | Public IPv4. A temporary hostname (`x.y.z.w.sslip.io` or the provider's) is enough for Days 1-2 |

**Software** — versions matter more than usual here:

- **PHP 8.4.1 or later.** Not 8.3: `spatie/laravel-activitylog` requires `^8.4`,
  and `composer.json` pins the platform to exactly 8.4.1.
- **MySQL 8.x. Not MariaDB** — no native JSON type, and the schema depends on it.
- **Redis** with the **phpredis** extension, not predis. Cache tags are used
  throughout and predis handles them differently.
- Nginx, Supervisor, Composer 2, git.
- PHP extensions: `bcmath ctype curl dom exif fileinfo filter gd iconv intl json
  libxml mbstring openssl pcntl pcre pdo_mysql posix redis session simplexml
  tokenizer xml xmlreader xmlwriter zip zlib`
- Image optimiser binaries: `jpegoptim optipng pngquant gifsicle webp`
- **ffmpeg is not needed in production** — the shipped video renditions are
  already transcoded. Only `VideoLibrarySeeder` wants it.
- Node is **not** needed if we build assets locally and ship `public/build`,
  which I recommend: it is gitignored today, so two machines can otherwise serve
  different sites from one commit.

**Access:**

1. SSH to a sudo-capable user. Send me the host and user; **put my key on it
   rather than sending a password**, or set a password you rotate immediately
   after.
2. Firewall open on 22, 80, 443. Say if you want me to configure ufw.
3. **Do not paste secrets into this conversation.** Put R2 credentials and the
   Sentry DSN in a file on the server — `/root/masar-secrets.env`, mode 600 —
   and tell me the path. I will read them there and they never enter a
   transcript.
4. R2: account id, access key, secret, bucket name, endpoint. Without these
   `MEDIA_DISK` stays local and the box carries the media.
5. Sentry DSN, or say "skip" and errors go to the log driver for now.

**One decision I need from you:** build assets on the server (needs Node 20+) or
build locally and ship `public/build`. I recommend shipping built.

## Deploy tooling — written, and the two scripts that could be tested were

`deploy/` now holds `provision.sh`, `deploy.sh`, `backup.sh`, `restore.sh` and
the nginx, PHP-FPM and Supervisor configs.

**Docker: skipped, deliberately.** The daemon was down and there is no local
nginx or supervisord, so a container would have bought one syntax check of one
config. Instead `deploy.sh` runs `nginx -t` and refuses to reload on a bad
config — durable protection on every deploy rather than a check I ran once. An
invalid config that reloads takes the site down; one that fails validation takes
nothing down.

**Backup and restore were run for real against the local database**, which is
the part that could be genuinely tested:

    backup.sh   -> 51 tables, gzip integrity-checked, manifest written
    restore.sh  -> cache poisoned first (2 links in <header>), restored,
                   then 14 links, ticker rendered, smoke check passed

The restore ends with `cache:clear` and then `masar:recount`, and refuses to
exit until `masar:smoke` passes. That ordering is asserted by a test — clearing
*before* the import would simply cache the old data again on the first request.

## `masar:smoke` — the post-deploy check

Seven checks, all reading the artefact rather than its inputs:

| check | what it would have caught |
|---|---|
| homepage responds | — |
| masthead has navigation | the empty cached snapshot, counted in `<header>` not in the table |
| market ticker | the same, and it distinguishes the `as_of` gate working from the band being hidden |
| robots.txt comes from the route | the static file that shadowed it for the life of the project |
| queue worker is running | 1,771 jobs queued with nothing consuming them |
| demo mode | `--expect-demo=on\|off` fails the deploy if the indexing switch is not where we believe |
| built assets present | a manifest pointing at files that are not on disk |

Each was verified by breaking it: restoring `public/robots.txt` fails the robots
check, poisoning the cache fails navigation and ticker, and `--expect-demo=off`
fails against a site in demo mode. All with diagnostics that name the cause.

`deploy.sh` ends by running it, so a deploy cannot report success while the site
is wrong.

## Decisions taken, as agreed

- **`public/build` is no longer gitignored.** Assets are built locally and
  committed, so the commit and the served bundle cannot disagree. A test asserts
  the ignore rule stays gone.
- **`config:cache` is never run.** It stops `env()` being read, so a changed
  `.env` silently does nothing — the worst entry in the stale-snapshot register.
  Routes, views and events are cached; config is not. A test asserts it, matching
  commands rather than comments.
- **ufw: 22, 80, 443, nothing else**, in `provision.sh`.
- **Secrets** are read from `/root/masar-secrets.env` on the box. None in the
  repo, none in this transcript.

## Decay check

`php artisan test` — **725 passed, 2,331 assertions, 45.53s**. Decay green,
smoke green.

## Still blocked on

The host and user. Everything above runs on a real box within an hour of getting
SSH; `provision.sh` expects a bare Ubuntu 24.04 and is idempotent.

---

# Media to R2, and Day 3-4 begun

Date: 2026-09-15.

## R2 — wired to fail loudly

`config/filesystems.php` had `'throw' => false` on the r2 disk. A misconfigured
object store does not fail at boot; it fails on the first write, and with
`throw => false` that write returns `false` and is discarded — an editor uploads
a photograph, sees no error, and the file is nowhere. Now `throw` and `report`
are both true: loud is the only safe setting for a remote disk.

`masar:smoke` gained a **media disk round-trip** — it writes a byte, reads it
back and deletes it. Inspecting configuration proves nothing; a store that
accepts a write and loses it passes every config check there is. And when
`MEDIA_DISK=r2` it first asserts the four credentials are present, naming the
missing ones:

    media disk  FAIL — MEDIA_DISK=r2 but missing: R2_ACCESS_KEY_ID,
                R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT

So a missing credential fails the deploy rather than silently writing nowhere.
The local `media` disk stays the default and fully working, which is the
fallback: switching back is one env var.

`provision.sh` checks `/root/masar-secrets.env` exists and is mode 600, and
refuses if it is not. It never reads the values into any log.

## Day 3-4 — audit first

I crawled every public page type and every internal link before changing
anything.

**Three genuinely dead links, and they were seeded menu items:**

    /ar/team          فريق التحرير      404
    /ar/corrections   سياسة التصحيح    404
    /ar/advertise     أعلن معنا         404

They have sat in the footer since the menus were seeded. Nothing caught them —
the menu rendered, the links were blue, and every test asserted the page they
sat on returned 200. All three are now real pages, and **a test asserts every
url in every seeded menu resolves to a route**, which is the class of bug rather
than the three instances.

Re-crawled after: **112 internal links, 0 broken.**

**The team page names nobody, deliberately.** The obvious fix was to list the
eight demo people, and that would invent a masthead of staff for a real brand —
§5's fabrication rule applied to people rather than quotes. `people.is_expert`
is never seeded true, so the page describes how the newsroom works and says
plainly that the demo people are not real. A test pins both halves.

**Two "empty sections" I did not fix, because they were my own false
positives.** My heuristic flagged any `<section>` with no `<a>`, `<li>` or
`<img>`; on `/ar/markets` that caught the instrument grid (text cells by design)
and the newsletter band (a form, not links). Both render correctly. Worth
saying because the fix would have been damage.

## The supply problem, stated rather than shipped thin

You asked me to say so rather than ship thin. Measured:

| category | published |
|---|---|
| business | 16 |
| stories | 15 |
| insights | 14 |
| saudi | 10 |
| startups | 10 |
| global | 7 |

| content type | published |
|---|---|
| news | 30 |
| interview | 9 |
| explainer, opinion, report, analysis | 8 each |
| sponsored | 1 |

Three things follow:

1. **There is no "Economy" category.** The plan names "Economy, Business and
   Startups"; the six that exist are business, global, insights, saudi,
   startups, stories. Either Economy is a new category needing its own content,
   or the plan means the existing سعودية والأسواق / الأعمال والشركات pair. I
   need your reading before building a page for a section that does not exist.
2. **Podcast has no content type at all** — no `podcast` case in `ContentType`
   and zero rows. The podcast section cannot fill from demo content in any
   arrangement. It needs either an episode model and real audio (Day 7 work) or
   removal from the page.
3. **`global` at 7 articles** is the thinnest category and will under-fill a
   three-across grid the moment anything filters it.

**Opportunities filters are already SQL-side and already done** — sector,
region, potential, open-only and deadline are all `when()`/`whereHas()` clauses
in `OpportunityController`, narrowing before pagination. That plan line can be
struck.

Still to do in this block: the category pages as genuinely distinct templates
rather than one shared view, the company profile as the content-graph payoff,
and person/author/topic/issues depth.

## Decay check

`php artisan test` — **730 passed, 2,341 assertions, 46.94s**. Smoke green,
decay green, 0 broken internal links.

---

# Day 3-4 continued — removals, category shapes, company profile

Date: 2026-09-15.

## Economy — struck, and it was never in the code

Nothing to remove. No `economy` category, no nav item, no template. The only
matches were prose about الاقتصاد in the about page and a category description,
which are correct and stay. It existed only in the plan. Struck there and
recorded in DEFERRED.md so it does not come back off an old sitemap.

## Podcast — removed

Gone from `HomepageSectionType`, the homepage view, the seeder, and the
component file is deleted. A migration removes any surviving `homepage_sections`
row, because the enum case no longer exists and a leftover row would throw on
the cast the next time a layout is read — a fresh install and an existing
database have to converge.

Worth recording what it was doing: the section was configured with
`content_type => 'interview'`, so it presented **interview articles under a
heading promising audio**. That is worse than an empty section, because it looks
filled. Nothing in the suite objected.

`DEFERRED.md` now exists and carries podcast with the reason, plus reports,
issues, English, reader accounts, entity-aware dedupe, Saudi regulator coverage,
Meilisearch, read-time importance, ads, the 2.2 AA finding, the LCP figure and
the 72-article floor.

## Category shapes now follow supply

The measured defect, before touching anything:

| category | featured | rendered as |
|---|---|---|
| saudi | 1 | three-across grid, **two holes** |
| business | 2 | three-across grid, **one hole** |
| global | 2 | three-across grid, **one hole** |
| insights, startups, stories | 3 | full grid |

Half the categories were rendering a grid with gaps. The shape is now chosen
from the count: three features get the grid, two get a two-across, **one gets
the full-width overlay treatment**, none omits the block entirely.

`global` is the case that makes the rule explicit. It carries 7 articles because
global coverage only runs when it touches the Saudi market — it is *supposed* to
be thin, and now it reads deliberately at that size instead of advertising two
missing cards. Five tests pin the rule, including that exactly one image stays
eager whatever the shape.

## The company profile

The page was a header and a flat list. It now answers three things a tag cannot,
from `CompanyProfileQuery` — a query object, not a fattened controller.

**Key facts** as a definition list rather than a row of tags: sector, base,
founding year, ticker, official site. These are what a reader checks before
quoting a company and they should read as pairs.

**Shape of coverage** — the count by content type. On the demo data: خبر 7,
تحليل 5, رأي 2, مقابلة 2, تقرير 1. A list of articles is something any CMS
produces; the distribution is what the graph knows.

**Who else appears in the same stories.** This is the one nothing else on the
site does and the reason `entity_mentions` is polymorphic rather than a set of
pivots: take every published piece mentioning this company, then every *other*
entity in those same pieces, ranked by co-occurrence. Two statements and one
hydration pass per type — no query in a loop. On demo data it surfaces six
people and six companies with real counts.

**Timeline by year** — and it does not render, correctly. All 72 demo articles
are dated 2026, so there is one bar, and a one-bar chart states nothing. The
section hides below two years. That is a demo-content limitation rather than a
defect, and Day 7 is where it gets fixed by dating content across years.

Measured on the page: **22 queries**, no query repeated more than twice, 1,031
words against 381 before. Seven tests, including a bounded-query-count test so
the co-mention hydration cannot quietly become an N+1.

Two sections my crawler flags as "empty" on that page are its own false
positives again — the coverage badges are spans, not links, and the newsletter
band is a form. Both are full of content.

## Struck from the plan

- **Opportunities SQL-side filters** — already done. All five narrow before
  pagination.
- **Economy** — see above.
- **Podcast** — see above.

## Decay check

`php artisan test` — **742 passed, 2,369 assertions, 45.98s**. Smoke green,
decay green, **112 internal links, 0 broken**.

Still in this block: person, author, topic and issues.

---

# Day 3-4 complete — person, author, topic, issues

Date: 2026-09-15.

## One query object, four pages

`CompanyProfileQuery` became `EntityProfileQuery` rather than growing a sibling.
The morph alias is read off the model, so the same class answers the same
question for a company or a person, and a second near-identical class cannot
drift from the first.

Added `sections()` — the categories a subject actually turns up in — and a
static `entitiesInArticles()` for the topic case.

## Person

The same graph questions, where they earn their place. **Coverage-shape was left
off deliberately**: a breakdown by content type says something about a company
and very little about an individual.

What it now shows: **what they are quoted on**, by section with counts, and
**who they appear beside**, companies and people ranked by co-occurrence. For an
expert, being repeatedly quoted alongside one company is a fact a reader should
be able to see.

Renders nothing at all for a person we have never quoted — no headings with
empty space under them.

## Topic

A topic is not a mentionable entity; it links through `article_topic`, so the
co-mention question cannot be asked the same way. It runs the other direction
instead: **given these articles, who is in them.** "Most present in this topic",
companies and people with counts.

That distinction is the interesting one. A tag can tell you what is filed under
2030. Only the graph can tell you who is in those stories.

## Author — structure over depth

It already had Person JSON-LD, a published count and a publishing-since year
drawn from our own records. Two structural additions:

- **`ProfilePage` wrapping the Person**, with `mainEntityOfPage`. That is the
  shape Google documents for author pages and what lets articles be attributed
  to a person rather than to a URL.
- **Breadcrumbs**, which the page did not emit.

**What I did not add, and why:** no `description` and no `sameAs`. There is no
bio field on a user and no verified profile link, so both would be invented — a
fabricated credential is §5's fabrication rule pointed at the author rather than
at the story. A test asserts neither key appears, so nobody adds them later to
"improve E-E-A-T".

## Issues — already right, left alone

Cover, "in this issue", then chapters grouped by the categories represented.
Five populated sections, 768 words, no empty blocks. It renders from featured
topics rather than an issue record, which is recorded in `DEFERRED.md` as a
schema gap rather than a page defect. Nothing to fix here.

## The N+1 guard, rewritten

My first version asserted "at most 3 queries" and failed at 4 — the real figure,
and still bounded. Rather than raise the number I changed what it proves: the
same work over **three times the data must cost the same number of queries**. A
fixed threshold only says "not more than n today" and gets adjusted upward until
it means nothing; boundedness is the property that actually matters.

## Recorded

- CLAUDE.md §5 now carries the supply rule verbatim, with the measurement that
  produced it.
- The Day 7 slot carries the requirement to **date demo content across years**,
  so the coverage timeline has something true to draw. The component is built
  and tested and correctly hides itself at one year; it is waiting on content.

## Decay check

`php artisan test` — **748 passed, 2,388 assertions, 47.18s**. Smoke green,
decay green, **112 internal links, 0 broken**.

Day 3-4 is done. Next: Day 5 — notifications, ads and sponsored content, the
homepage composer's section ordering in the UI, and the read-time importance fix
with the stale-snapshot inventory.

---

# Day 5 (part) — read-time importance and the inventory

Date: 2026-09-15.

## Read-time importance — done, and it took four attempts to get right

`importance` was a number computed from four inputs, one of which decays against
`now()`, and then stored. It was wrong the moment it was written.

The split: **`importance_base`** holds the three durable inputs — source trust,
document type, entity match — already weighted. Recency is added back at read
time, and **in SQL when ordering**, because an accessor cannot appear in
`ORDER BY` and sorting a paginated collection in PHP sorts one page at a time,
which looks right and is not.

`importance` is kept unchanged, meaning what it always meant: what we thought at
ingest. That is the number an editor saw, and it is worth keeping for audit —
redefining a column in place would have made every historical row lie.

**Three real faults surfaced while verifying SQL and PHP produced the same
order.** I nearly wrote each off as ties:

1. **SQL truncates minutes** with `TIMESTAMPDIFF(MINUTE, ...)` while PHP used
   fractional ones. The two arithmetics differed at the eighth decimal —
   invisible until it reorders an exact tie.
2. **PHP called `now()` per item**, so two items compared microseconds apart got
   different ages. A PHP-side sort was non-deterministic *against itself*.
3. **Ties had no tiebreak.** On a paginated query that shows one row on two
   pages and skips another, which reads as items vanishing from the inbox.

Fixed: whole minutes, an injectable clock, and `detected_at` then `id` as a
stable tiebreak. Now verified identical across 12 items.

`liveImportanceScore()` returns the unrounded value and `liveImportance()`
rounds it, because rounding before comparing reorders near-ties — 67.9 and 67.6
both read as 68.

Seven tests, written as invariants rather than thresholds per §7: *the same
ordering in SQL and in PHP*, *two identical queries return the same order*, *an
item drops as it ages with nothing rewriting the row*.

114 items re-derived; 90 importance values changed.

## The inventory

`tasks/STALE-SNAPSHOT-INVENTORY.md` — swept against the code, not from memory,
and listing entries whether or not anything is currently broken.

Five categories: **8 caches with a TTL**, **2 per-request memoisations**, **5
denormalised counters**, **5 computed-once columns**, **5 things outside the
application**. Three have already caused incidents and are marked as such.

The two worth acting on, neither currently broken:

- **`intelligence:company-names` is untagged** — no `Cache::tags(...)->flush()`
  reaches it, which is the exact property that makes a stale cache hard to find.
  The `entities:name-index` cache built after that lesson is tagged.
- **`RobotsPolicy` caches for six hours** — the only entry facing a third party.
  A publisher who tightens `robots.txt` is not honoured for up to six hours.

And the note that matters most: **none of this is visible to the test suite**,
which runs on the `array` cache driver. `masar:decay` and `masar:smoke` are the
only things that read the rendered page, which is why neither may be weakened
into a database query.

## Composer section ordering — already closed

Checked rather than assumed. The UI exposes it twice: drag-reorder calling
`reorderSections`, and keyboard buttons calling `moveSection` for the same
operation. Both persist `sort_order`, both are covered by existing tests
including the refusals — already first, not a direction, someone else's layout.

**No hole in the control layer.** Reordering has never needed a seeder edit.

## Decay check

`php artisan test` — **755 passed, 2,397 assertions, 47.16s**. Smoke green,
decay green.

## Still open on Day 5

Notifications (in-app centre, email for critical rules) and ads/sponsored
content. Neither started.

---

# Inventory rulings, and the sponsored disclosure

Date: 2026-09-15.

## Both inventory items closed

- **`intelligence:company-names` is tagged** (`entities`). It was unreachable by
  every flush we have, which is the property that matters rather than the hour.
- **`RobotsPolicy` drops 6 hours to 1.** Still saves 23 of every 24 daily robots
  fetches, and bounds to an hour how long we keep fetching from a publisher who
  has just told us to stop.
- CLAUDE.md §6 carries the tagging rule verbatim, so it is a rule rather than a
  habit. The inventory is named there as the register.
- The Day 10 slot now carries `tasks/STALE-SNAPSHOT-INVENTORY.md` into the
  handover explicitly.

## Ads — starting with the part that is the commitment

Before any placement or campaign: **the sponsored label was on the article page
and on none of the six card weights.** A sponsored piece sat in a homepage grid
or a category list with nothing to distinguish it until a reader clicked
through.

That is worse than an oversight, because `/ar/advertise` — which I wrote last
round — tells advertisers the label appears "in every place it appears on the
site, وفي كل مكان تظهر فيه ضمن الموقع". I made a disclosure promise on a public
page that the code did not keep.

`x-article.sponsored-flag` now renders on all six weights. It takes no condition
from the caller: **a card cannot choose not to disclose.**

One thing the tests caught that I would have shipped: my first placement in
`card-overlay` landed inside `@if ($size !== 'sm')`, so the small variant
disclosed nothing — and the small variant is the one that appears in grids,
which is where most readers meet a sponsored piece. A disclosure that disappears
on the common variant is not a disclosure. Moved outside every size condition.

Ten tests: each card weight individually, a real listing end to end, the
negative case, the gate refusing to publish a sponsored article with no sponsor
named, and — the one that matters — **a file-level check that every
`card-*.blade.php` carries the component**, so a seventh card cannot be added
without it.

## Decay check

`php artisan test` — **765 passed, 2,409 assertions, 47.06s**. Smoke green.

## Still open on Day 5

The ad mechanism itself — placements, a campaign with dates, a creative, an
impression counter — and notifications. Scope noted: mechanism, not
marketplace. No targeting, no bidding, no dashboard beyond a count.

---

# Day 5 — the ad mechanism

Date: 2026-09-15.

## Built: mechanism, not marketplace

Three tables — `ad_placements`, `ad_campaigns`, `ad_creatives` — a slot
component, a click redirect, a buffered impression count, and a Filament
resource. No targeting, no bidding, no dashboard beyond impressions and clicks,
as ruled.

**What is enforced rather than merely supported:**

- **The label is not a prop.** `x-ads.slot` renders «إعلان» and the advertiser's
  name outside the link, and a caller cannot turn either off — the same rule the
  sponsored flag now follows.
- **`rel="nofollow sponsored noopener"`.** Paid links do not pass ranking
  signals, and `sponsored` is the value Google documents for exactly this.
- **`alt` is NOT NULL on a creative.** An image an advertiser paid for that a
  screen-reader user cannot perceive is a slot billed and not delivered.
- **An expired campaign's link is dead, not merely uncounted.** The click route
  404s once the campaign stops, so a creative that is no longer servable is no
  longer followable either.
- **Nothing renders when nothing is running.** An empty bordered box labelled
  "advertisement" is a worse page than no box.
- **Impressions buffer in Redis**, flushed every five minutes. §5 forbids
  `increment()` per request, and an ad slot sits on more pages than an article
  does. The flush reads and decrements per field, so impressions counted while
  it runs are not discarded.
- **A failure in an ad never reaches the reader.** Serving and counting both
  swallow their own errors: an advertisement is the least important thing on any
  page it appears on.

Ten tests, weighted towards the commitments rather than the serving — the label,
the alt text, the expiry, the dead link, and the buffer not writing per request.

## One thing the sweep turned up

**`articles.views_count` is written by nothing.** No code anywhere increments
it. It is *read* by the homepage's most-read section and the admin's most-read
widget, so both rank on a number only the seeder has ever set.

That is not a stale value — it is an unimplemented one being presented as a
measurement, which is the same family as the podcast section presenting
interviews. Recorded in the inventory; it needs either an implementation or
those two surfaces need to stop claiming to rank by readership.

## Decay check

`php artisan test` — **775 passed, 2,428 assertions, 48.13s**. Smoke green,
decay green.

## Still open on Day 5

Notifications — the in-app centre and email for critical rules. Then Day 6
hardening, which now carries the public-commitments sweep.

---

# Readership — the ninth one, implemented

Date: 2026-09-16.

## Built to the five rules

| rule | how |
|---|---|
| Buffer, never `increment()` per request | Redis hash `views:buffer`, flushed every 5 minutes by `masar:flush-views` |
| Once per visitor per article | `SET NX EX` on `views:seen:{article}:{visitor}`, keyed on the existing `visitor_id` cookie. Atomic, so two concurrent requests from one visitor count once |
| No bots | A **user-agent** list — deliberately separate from `not_found.ignore_patterns`, which matches paths. A crawler fetching a real article is not a 404 and would otherwise rank it. An empty user agent is also not a reader |
| Rolling window | `article_views` rows per article per day; ranking sums the last 7 days |
| Fail silently | Serving and counting both swallow their own errors. A test asserts the article page still returns 200 with Redis throwing |

`views_count` survives as the lifetime total, but **nothing ranks on it any
more.** Both surfaces read `article_views`, which only real readership fills —
so a seeded number cannot rank anything, which was the whole complaint.

## Until there is real data, both surfaces hide

The homepage section returns an empty collection and disappears. The admin
widget uses an **inner** join, so an article with no recorded reads is absent
rather than ranked last with a zero, and its empty state says: «يظهر الترتيب
حين تُسجَّل قراءات حقيقية. لا نرتّب على أرقام تجريبية.»

Verified live: with `article_views` empty the section was absent from the
homepage; after one genuine read and a flush, it appeared.

The admin column now reads «قراءات هذا الأسبوع» rather than a lifetime total,
because the heading says seven days and the number has to mean the same thing.

## Two faults found while verifying, one of them a production bug

**The flush died on an article that no longer existed.** A foreign key error
killed the *entire* flush, so one missing row would have cost every article's
counts — on every run, repeatedly, until someone cleared the buffer by hand. It
now drops the orphan and keeps going. This is not hypothetical: an editor
deleting an article between a read and a flush reproduces it exactly.

**Redis is shared between the test and development databases.** The suite uses
the real Redis connection — `CACHE_STORE=array` does not touch the Redis facade
— so article ids from a test run appeared in the development buffer, which is
how the orphan case surfaced at all. It also broke test isolation: dedupe keys
carry a 24-hour TTL and `RefreshDatabase` resets article ids, so "article 1,
visitor X" stayed marked as seen and the next run counted nothing. Both recorded
in the inventory; the tests now clear their own keys.

Eight tests, weighted at the ways the naive version is wrong: a refresh counting
twice, a crawler ranking a story, a lifetime winner holding the slot forever, a
counting failure taking the page down, and the orphan.

## Decay check

`php artisan test` — **783 passed, 2,450 assertions, 47.76s**. Smoke green,
decay green.

## Still open on Day 5

Notifications. Then Day 6 hardening with the public-commitments sweep.

---

# Redis isolation, and notifications — Day 5 closed

Date: 2026-09-16.

## Redis isolation — fixed, not recorded

Tests now run on Redis databases **14 and 15**, flushed once at boot, and
`Tests\TestCase` **refuses to run** if the numbers did not take effect —
mirroring the database guard, because we already learned `force="true"` does not
always win under `artisan test`. Verified by pointing it at database 0 on
purpose:

    The test suite is pointed at Redis databases 0/15, which development uses.
    Refusing to run: it would read and write the development buffers.

That closes the case where the suite could pass or fail on what ran yesterday:
dedupe keys carry a 24-hour TTL and `RefreshDatabase` resets article ids, so
"article 1, visitor X" stayed marked seen between runs. Development Redis
cleaned of the residue.

## Notifications

**In-app centre**: Filament database notifications, polled every 60s. Database-
backed rather than broadcast — a source dies at 3am and the person who can fix
it arrives at 9, so the notice has to survive nobody being logged in. A
websocket server to deliver a handful of notices a week would be infrastructure
with nothing behind it.

**Email for critical rules**: two, and only two. A source being disabled
(existing) and an inbox item scoring above `alert_importance`, default 75.

The alert is built around *not* firing, because the failure mode of an alert is
noise rather than silence:

- **A duplicate never alerts.** The story already arrived; telling someone twice
  about one event is the fastest way to get a channel muted.
- **Null threshold switches it off** without deleting code.
- **Only roles that can act** — a notification nobody can act on is the silent
  failure with extra steps.
- **No publisher prose travels.** Raw text is internal for its whole life and
  does not reach an email either.

One thing worth naming in the wiring: **the alert fires after the transaction
commits, never inside it.** A notification raised inside a transaction that then
rolls back tells an editor about an item that does not exist — and the queued
mail has already gone. Outside, the worst case is an item stored with no alert,
which the inbox still shows.

Seven tests, six of them about staying quiet.

## Decay check

`php artisan test` — **790 passed, 2,466 assertions, 48.70s**. Smoke green,
decay green.

**Day 5 is complete.** Notifications, ads, composer ordering (already closed),
read-time importance, and the inventory.

## Next: Day 6

Hardening, carrying two sweeps now:

1. Every public commitment against what the code enforces.
2. **Every number and label the site shows a reader** — reading time, article
   counts, «الأكثر قراءة», trending topics, mentions counts, issue statistics,
   the admin dashboard. For each: what writes it, when, and is it true. Reported
   as a list, not fixed as I go.

---

# Day 6 — the numbers sweep

Date: 2026-09-16. `tasks/NUMBERS-SWEEP.md`. Nothing fixed, as instructed.

## The count

**26 numbers. 3 wrong, 1 unverifiable-but-correctly-framed, 22 true.**

Better than I expected, and the three are not evenly serious.

## Wrong

**1. `mentions_count` on companies and people — buckets A and B.** It counts
**every** mention including drafts. §5 says counters count published rows only,
and `SyncArticleTopics` does exactly that — so the two actions disagree with
each other. Measured: all eight companies checked are inflated.

    company 1  held 20  published-only 17
    company 2  held 19  published-only 14
    company 5  held 10  published-only 7

It is not printed on the page, but `ComposeHomepage` filters the entities
section on `mentions_count > 0` — so **an entity covered only in drafts can
appear on the homepage as though we cover it** — and the admin list sorts on it.
That is why it lands in both buckets.

**2. `reading_time` — bucket B.** `PublishArticle` persists the estimate at
publish and the stored value then wins over the accessor, so an article edited
after publication keeps the old figure until republished. Shown on ten surfaces
including every card. The estimate itself is sound.

**3. `views_count` — buckets A and B.** Fixed on 2026-09-16; listed because it
is why this sweep exists.

## Unverifiable, and correctly framed

**The market figures.** No feed, no licence, every figure editor-entered. They
carry `market.as_of`, undated figures do not render at all, the source line says
they are sample figures, and the chart is drawn without a value axis so an
entered shape is not presented as measured.

That is the right treatment of a number we cannot verify: labelled, dated,
attributed, and it disappears rather than degrading. Worth naming as the pattern
the other two should be judged against.

## Notable among the true ones

`topics.articles_count` **is** published-only and verified correct — it is the
counter `mentions_count` should have copied, in the same codebase, written by
the sibling action.

Everything I built on the entity pages — coverage, timeline, co-mentions,
sections — computes live from published rows, so there is nothing to drift.

Three decorative numbers, all harmless: summary-box ordinals (`aria-hidden`),
source-list numbering, and the footer year. None claims anything.

## What I would fix, if asked

1. **`mentions_count`** — a rule violation, measurably wrong on every row
   checked, and it decides homepage content. The fix is four lines;
   `SyncArticleTopics` already has the filter to copy.
2. **`reading_time`** — recompute on save, or drop the column and always
   estimate. The second cannot drift.
3. Nothing else.

## Decay check

`php artisan test` — **790 passed**. Smoke green.

Still on Day 6: the public-commitments sweep, then the security and
accessibility passes.

---

# Day 6 — both fixes

Date: 2026-09-16.

## `mentions_count` — four copies, not two

I estimated four lines. It was four *implementations*.

The rule lived in `SyncArticleTopics` (correct), `SyncArticleEntities` (no
filter at all), `RecountCommand::driftFor()` (no filter) and
`RecountCommand::topicDrift()` (its own copy of the correct one).

The consequence was worse than the original bug: **the drift query agreed with
the bug**, so `masar:recount` reported *zero rows out of date on data where
every row was wrong* — and running it silently rebuilt the inflated values over
the corrected ones. I watched it undo my own fix before I understood why.

All four now share `App\Support\PublishedContent`, which holds one definition
of a live article and one of a live opportunity. `RecountCommand` calls the
actions rather than keeping its own SQL, the way it already did for topics.

Mentions on published **opportunities** now count too — a mention on a published
opportunity is coverage as much as one on an article, and the old query counted
both without checking either.

Verified end to end: 12 companies and 7 people reported out of date, rebuilt,
then zero. Values checked row by row against a published-only count.

## `reading_time` — column dropped

Always computed. Your reasoning was the deciding one and it is now in the
migration: recomputing on save works until the next code path that writes an
article without passing through it, which is exactly how the bug existed — the
accessor was always right and the column overrode it.

`PublishArticle` no longer freezes an estimate. The factory no longer seeds one.
Ten surfaces read an accessor that cannot be stale.

## Eight tests broke, and one of them was right

Six were fixtures assuming drafts count as coverage — corrected to published,
with a note saying why, since `publishableArticle()` means "could be published"
rather than "is".

One asserted `getRawOriginal('reading_time') > 0` on a column that no longer
exists. Its intent — a live article never shows a blank reading time — is now
structural rather than enforced at publish, and the test says so.

**And one was already correct and I broke it.** `it('syncs topics and keeps the
published-only counter correct')` deliberately used a draft to assert the
counter stays at zero — my blanket replacement made its article published and
destroyed its premise. Reverted, with a comment so the next blanket edit leaves
it alone.

## Decay check

`php artisan test` — **796 passed, 2,475 assertions, 52.93s**. Smoke green,
decay green.

Both fixes done; the other 23 numbers untouched. Next on Day 6: the
public-commitments sweep, then security and accessibility.

---

# Day 6 — the public-commitments sweep

Date: 2026-09-16. `tasks/COMMITMENTS-SWEEP.md`. Nothing fixed, as with the
numbers sweep.

**19 claims. 14 kept. 3 intended. 2 broken.**

## Broken 1 — and it is the worst thing I have found here

The editorial standards page tells readers:

> «لا يستطيع الكاتب تدقيق مادته بنفسه — هذا قيد مفروض في النظام، لا عرف
> يُتبع أحيانًا.»

*A writer cannot fact-check their own article — a constraint imposed in the
system, not a convention sometimes followed.*

`ArticlePolicy::factCheck()` implements that rule correctly **and nothing in the
application calls it.** Its only references are its own tests. The transition
that stamps the fact-check authorises `transition`, and `transition` returns
**true** when the user owns the article.

Proven against the real path rather than read off the source:

    policy says factCheck allowed? NO
    transition succeeded: editor_review
    fact_checker_id recorded: 3  (author_id 3)
    SELF-CHECKED: YES — the writer checked their own work

A writer moves their own article through fact-check and the system records them
as its fact-checker, while the page tells readers that is impossible.

It is the same shape as everything else caught here — policy exists, tests
pass, nothing calls it — but this one is a claim about **editorial integrity**,
stated as a system guarantee, on the page a sceptical reader would check first.

**It wants code, not rewording.** The intent is unambiguous.

## Broken 2 — mine, from two days ago

The corrections page describes a correction line appended to the article, and a
material correction shown at the top. **There is no correction anywhere in the
system** — no column, no model, no component. `updated_content_at` exists and
feeds only `dateModified` and the sitemap; no reader ever sees it.

I wrote that page and described a feature that does not exist. Build it or
reword it; the current state is neither.

## Intended, not enforced

Three, all fine as editorial promises and none enforceable: quotes attributed to
consenting people, paid content not passing the same editorial path, and
independence from advertisers. They read as system guarantees in places, and
claim 1 shows what that costs when the guarantee is absent.

## One chain, not a false claim

**Every contact email is empty.** The contact page degrades honestly — «لم يُضف
بريد لهذا الفريق بعد» — but corrections, advertise and about all route readers
there as the way to reach us. So the corrections policy tells a reader how to
report an error and the route ends in an empty page.

Same shape as the crawler's `contact@masar.sa`: an accountability route that
does not resolve. Settings, not code.

## Decay check

`php artisan test` — **796 passed**. Smoke green.

Still on Day 6: security and accessibility.

---

# Day 6 · claims closed out

## Claim 1 — fixed, and it was not alone

`TransitionArticleStatus` now authorises `factCheck` when an article leaves the
fact-check stage, and enforces the invariant itself rather than trusting the
policy to be asked. Six tests drive the real transition path; none is a policy
unit test, because a passing policy unit test is what concealed this for the
life of the project.

**The general guard found two more.** Sweeping `authorize('x'|can('x'|cannot('x'`
across `app/ resources/ routes/`, excluding `app/Policies/` and excluding
Filament's standard CRUD verbs (which the panel calls implicitly), leaves
exactly three custom methods on `ArticlePolicy` — and before today all three had
zero callers. Neither of the other two was harmless:

| method | what was actually exposed |
|---|---|
| `publish` | The panel disabled the publish button on **gate failures only**, never on the permission. The `seo` role carries `article.update.any` and no `article.publish`, so an SEO specialist could open any article sitting at `ready` with a clean gate and was shown a live green publish button whose only possible answer was an exception. |
| `seo` | The SEO tab was **ungated entirely**. It carries the `noindex` toggle, so any writer who could edit an article could remove it from search — and that change leaves no visible trace on the page. |

Both are wired now. `publish` drives the button's disabled state, with the
tooltip separating "you may not publish" from "the gate has four things to say".
The SEO tab is hidden rather than disabled: Filament does not dehydrate a hidden
field, so a writer's save cannot blank meta values it never showed them.

`tests/Feature/Filament/PolicyReachabilityTest.php` — 7 tests against the panel.
**Four of the seven fail against the previous code**, including the one that
writes `noindex` as a writer, which is the check that says the fix does
something rather than describing something.

The other four policy classes have only standard CRUD verbs. No further orphans.

## Claim 2 — the page rewritten, the gap recorded

Rewritten as an editorial commitment. Every sentence is first person and the
page says in as many words that the corrections log is being built and is not
there yet.

One thing was made true rather than reworded. `updated_content_at` was written
on every edit and surfaced only in the sitemap's `lastmod` and the JSON-LD's
`dateModified` — a promise kept to crawlers and not to readers. The byline now
shows «آخر تحديث» when an article was edited more than five minutes after
publication. Verified on the rendered page, not the diff.

Corrections as a mechanism is in `DEFERRED.md` with what it needs. The part that
matters there is the index page: a list of every correction the publication has
made is what makes the rest of the policy credible, and it is the part a
body-text correction can never provide.

## The three intended claims — reworded

Each page now separates the sentence the system enforces from the sentence we
are trusted on, rather than letting the enforced one lend its authority to the
other.

- **Sources** — «لا تُنشر مادة دون مصدر» stays, because it is a gate rule.
  Attribution and consent moved to their own paragraph opening «التزام تحريري
  نلتزم به نحن ولا يفرضه النظام، لأنه ليس مما يمكن للنظام أن يفرضه».
- **Paid content** — «لا يمر على مسار التحرير نفسه» is gone. There is no separate
  workflow; sponsored articles move through the same statuses. What replaced it
  says which half is the system's: «ما يفرضه النظام هو الوسم».
- **Advertising** — «هذا ليس وعدًا عامًا» is gone, replaced by «هذا قرارنا في
  كيفية العمل، وهو ما تُحاسبوننا عليه», then the one part that is enforced:
  publish rights are tied to an editorial role.

## A fourth claim, found while rewording — enforced instead

The advertise page also said the sponsored label cannot be removed after
publication. Nothing stopped it: `is_sponsored` was a plain toggle on a
published article, and clearing it would have taken the label off every card and
off the schema.org block for a story readers had already seen disclosed.

Cheap enough to make true rather than weaken, so: an `Article::saving` guard
throwing `DisclosureCannotBeWithdrawn`, on the model rather than the form
because the column is writable from several paths; the toggle disabled in the
panel with the reason in its helper text, so an editor reads a sentence instead
of an exception; five tests. The way back is deliberate and visible — unpublish,
then unmark.

That moves the sweep to **17 kept, 3 reworded, 0 broken.**

## Written down

- CLAUDE.md §5, new subsection **"What the site claims about itself"** — the page
  rule the owner dictated, plus its corollary: a policy method with no caller
  outside its own tests is dead code that reads as protection.
- `tasks/HANDOVER.md` started. First entry is the contact-address chain:
  corrections, advertise and about all dead-end until the four
  `contact.*_email` settings are filled in Day 8-9, and the corrections case is
  the one that matters — the page tells a reader how to report an error we
  published and the route ends in an empty page.
- `DEFERRED.md` — corrections as a mechanism; orphaned policy methods (there is
  still no automated guard against the *next* orphan, only a grep run by hand);
  the stale Ads entry corrected, since the mechanism shipped; the read-time
  importance entry marked done.

## Decay check

    suite ........................................ 814 passed (2517 assertions)
    queue ......................... ok — depth 0 · failed 0 · worker running
    flow: publish an article ............................. ok — 72 published
    flow: article on the homepage ......................... ok — 18 sections
    flow: navigation renders in the masthead ..... ok — 14 links in <header>
    flow: homepage order is editable ............ ok — 18 distinct positions
    flow: newsletter stores a subscriber ........... ok — POST route present
    flow: source poll reaches the inbox  ok — 10 active sources · 114 inbox items
    flow: market ticker renders .............................. ok — rendered
    flow: redirect becomes a 301 ............... ok — 0 redirects configured
    public forms ............................... ok — 5 forms, all addressed

No decay. `migrate:fresh --seed` skipped as usual (~15 min, opt-in).

Still on Day 6: security and accessibility.

---

# Day 6 · the orphan sweep

CLAUDE.md §7 now carries the rule. Eleven categories swept; the full record is
`tasks/ORPHAN-SWEEP.md`. Four held real orphans, three of them doing harm rather
than sitting there.

## The pattern held, twice, in ways that were live bugs

**`UnpublishArticle` — an action nothing called, holding an event.**
`ArticleUnpublished` had a listener and a dispatcher, so both halves looked
wired. Nothing called the dispatcher. The panel's «أرشفة» button goes straight
through `TransitionArticleStatus`, which fired nothing — so archiving a live
story flushed no cache and left it on the homepage until the TTL expired.

Proven on the running site before the fix:

    article:      #8 — شركات التقنية المالية يتجاوز مرحلة المشاريع التجريبية
    on homepage:  yes
    archived:     status is now archived
    still on the homepage: YES  <-- the cache was never flushed

The dispatch moved into `TransitionArticleStatus`, beside the `ArticlePublished`
dispatch it mirrors. The action is deleted — its only other contribution was
rejecting a target the transition map already rejects. Same probe after:
`still on the homepage: no`. Five tests drive the transition; deleting the action
broke nothing in 837.

**Seven Filament resources with no gate — including the subscriber list.**
This is the serious one. Filament allows anything it cannot resolve a policy
for, so a resource with no policy and no access check is not locked by default;
it is open. Category, Country, Industry, Market, Person, Topic and Subscriber
were all in that state.

A **fact-checker** — permissions: view, transition, factcheck — could open the
newsletter subscriber list, read every reader's email address, and use the bulk
action that downloads the whole list as CSV. Measured per role, not inferred.

The docblock over `registerPolicies()` said the explicit registration existed so
a missing policy would fail loudly "instead of silently falling back to *no
policy, permission denied*". That sentence is backwards, and the seven open
resources are what the backwards reading cost. Corrected in place.

Fixed with `TaxonomyPolicy` (one class for the structured half of the graph —
`CompanyPolicy` and `OpportunityPolicy` were two verbatim copies of it and are
deleted) and `SubscriberPolicy` (read at `settings.manage`; create, update and
delete false for everyone). `PanelAccessTest` holds the invariant: every
resource has a resolvable policy or its own check. It names the offender when it
fails, and it does fail when a policy is removed.

After: writer and fact-checker see Articles, the Dashboard and the pipeline, and
nothing else. Editor-in-chief keeps everything.

## Two smaller ones

**`media.manage` gated nothing.** Four roles held it; no code checked it; there
is no media library for it to gate. The role editor offered a checkbox that
granted no capability. Removed from the permission list, the four roles and the
editor's area list — nothing changes for anyone, which is the point.

**Every publish gate rule was proven to fire**, one broken field at a time,
rather than assumed from the config being read. All nine fire. That category was
clean and is now guarded.

## What I got wrong, and am not fixing today

**The ads mechanism renders nowhere.** `x-ads.slot` is referenced by no
template. Under it: zero placement rows with nothing that creates one, and no
way to create a creative at all — no relation manager, no placement select, no
image upload. The serving half is real and correct; it has nothing to serve and
nowhere to draw.

I wrote "the mechanism shipped" in `DEFERRED.md` yesterday. That was wrong, and
the entry now says what actually exists. Left unfixed on purpose: placing slots
and seeding placements without a creative editor would add more inert surface,
and the creative editor is real work that would displace security and
accessibility. It needs a decision about when, not a patch today.

## One gap found on the way

`Subscriber::unsubscribeUrl()` has no caller — correct today, because the only
mail we send is the double opt-in confirmation and an unconfirmed address is not
on a list to leave. But **a confirmed subscriber has no way out**, and no
message carries `List-Unsubscribe` or `List-Unsubscribe-Post`. Nobody is
affected because nothing sends a newsletter. In `HANDOVER.md` as a condition on
the first send rather than in anyone's memory.

## Two false positives, recorded so nobody re-finds them

`web.advertise` and `web.team` look orphaned to a `route()` grep and are not —
they are reached through the footer menu, whose rows store literal URLs. Nothing
is broken, but a literal URL in a menu row is checked by nothing at build, test
or request time. In `DEFERRED.md`.

`masar:flush-views` and `masar:flush-ad-impressions` look unscheduled to a grep
for their signatures; both are scheduled by class name and `schedule:list` shows
them every five minutes.

## Decay check

    suite ........................................ 837 passed (2556 assertions)
    queue ......................... ok — depth 0 · failed 0 · worker running
    flow: publish an article ............................. ok — 72 published
    flow: article on the homepage ......................... ok — 18 sections
    flow: navigation renders in the masthead ..... ok — 14 links in <header>
    flow: homepage order is editable ............ ok — 18 distinct positions
    flow: newsletter stores a subscriber ........... ok — POST route present
    flow: source poll reaches the inbox  ok — 10 active sources · 114 inbox items
    flow: market ticker renders .............................. ok — rendered
    flow: redirect becomes a 301 ............... ok — 0 redirects configured
    public forms ............................... ok — 5 forms, all addressed

No decay.

Remaining on Day 6: security and accessibility.

---

# Day 6 · security and accessibility

## Security — the headline

**Stored XSS on every article page.** All five JSON-LD components encoded with
`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, which leaves `<` and `>`
literal, so a headline containing `</script>` ends the script element and
everything after it parses as HTML. Reachable by anyone who can type a headline,
executing for every reader.

Proven on the running site before touching the code — title set to
`</script><img src=x onerror=alert(1)>`, two working breakouts on the rendered
page, one in the NewsArticle block and one in the breadcrumbs. Closed with a
single `App\Support\JsonLd::encode()` carrying `JSON_HEX_TAG | JSON_HEX_AMP`,
called by all five. Re-rendered: zero breakouts, payload hex-escaped, Arabic
still readable. Both tests fail against the old flags.

## nginx `add_header` silently discards inherited headers

A `location` block that sets any `add_header` drops **every** `add_header` from
the server block. Three location blocks set `Cache-Control` — build assets,
fonts, and all images and video — so those paths were served with none of the
four security headers, and `nosniff` matters most exactly on uploaded-media
paths. Extracted to `deploy/nginx/security-headers.conf`, included at the server
level and inside each of those three, installed by `provision.sh` before the
site config that includes it. Added `Permissions-Policy`; HSTS is written and
commented, for the TLS cutover.

## Absence-is-permission, everywhere it could apply

| surface | result |
|---|---|
| `PUT /storage/{path}` | 403 unsigned — Laravel requires a signed URL |
| Horizon | 403 unauthenticated; the `viewHorizon` override holds |
| `/admin` | 302 to login |
| Filament export download | aborts 401 before anything else |
| API endpoints | none exist |
| Queued jobs | none carries a user or acts on anyone's behalf |
| Dev tooling in production | no Telescope, no Debugbar; `deploy.sh` installs `--no-dev` |
| Login rate limit | real, five attempts — **measured, not read** |

`SafeUrl::external()` checks the scheme where a URL is *drawn*, not only where
it was typed: article sources, company websites, footer social links and the ad
click redirect. Filament's `->url()` does reject `javascript:` and `data:`
(measured), but that is the one entry point that exists today, not the only way
a row arrives.

Four standing invariants: no model open to mass assignment (`Media` documented
as the one deliberate exception, since it extends Spatie's `$guarded = []`), no
JSON-LD encoded outside the helper, the locale parameter constrained at the
router, no secrets tracked.

## Accessibility

**axe: zero violations, 19 pages × 2 viewports** — 26 to 33 passing checks per
page, so the checker ran rather than silently skipped. It also found none of
what the pass actually fixed, which is the finding worth keeping.

**Focus.** Three text inputs carried `focus:outline-none` behind a 1px border
colour change: the newsletter band, the newsletter page, and the site search
box. Every text input a reader ever touches, with no keyboard indication of
where they were. Real rings now — mint on the dark band, g-600 on the light
ones, measured off the rendered page. A test forbids `focus:outline-none` in any
template, because the site-wide `:focus-visible` floor in `app.css` is only a
floor if nothing opts out.

**Target size (WCAG 2.2, 2.5.8).** Measured every tab stop rather than trusting
the note in `DEFERRED.md`, which said this had been accepted. Thirteen targets
under 24×24 on the desktop homepage, eleven on mobile, twenty-eight on the
article page — including the search icon at exactly 16×16 and the hamburger,
**the only route to the rest of the navigation**, two pixels short at 22px wide.

Now **zero on all three pages at both viewports**. The pattern was
`inline-flex min-h-6 items-center` rather than extra padding: the box reaches
24px while the text stays on its existing centre line, so nothing moved. The
rows were already 48px tall, which is why it cost nothing.

**Keyboard, drawer, motion — measured, all pass.**

    burger reachable by keyboard : true
    burger target                : 30x39  (was 22x39)
    drawer opens                 : true   (aria-expanded flips)
    focus moved into the drawer  : true   (onto the close button)
    focus trapped in the drawer  : true
    escape closes it             : true
    focus returns to             : فتح القائمة
    reduced-motion transition    : 1e-05s

**Video.** axe flagged `video-caption` as needing review. Measured with ffprobe:
**every clip on the site is silent** — no audio stream at all. So WCAG 1.2.2
(captions) does not apply and 1.2.1 (text alternative for video-only
prerecorded media) does, and there was none: the video media carried a filename
where a description belongs, which is the rule CLAUDE.md §5 already states for
images.

The player now renders a `<figcaption>` wired with `aria-describedby`, carrying
the footage description — from the caller, the clip's own alt, or the poster's,
since the poster shows the same footage — and «مقطع بلا صوت» when the clip is
known silent, because a reader who cannot hear needs to know the silence is the
clip and not a fault at their end. `has_audio` is measured once at seed time and
stored; **NULL says nothing either way**, since a page may not describe what
nobody established.

One bug caught inside that fix: the first ffprobe helper read `shell_exec`'s
output, which returns NULL when a command prints nothing — precisely the
no-audio case — so it reported every silent clip as unmeasured. Caught because
the backfill came back all-null on files ffprobe had just answered at the shell.
It reads the exit status now.

## Written down

- CLAUDE.md §5 — Filament authorisation defaults open; §5 accessibility — an
  automated checker is a floor, how a target grows without the design moving,
  and the silent-video rule; §7 — a package's protection is unverified until you
  have watched it refuse.
- `tasks/HANDOVER.md` — JSON-LD escaping is a security control with a test
  behind it, the nginx `add_header` trap, and the two package defaults we were
  wrong about.
- `DEFERRED.md` — the WCAG 2.2 target-size entry replaced: it said this was
  accepted, and it is now resolved to zero.

## Decay check

    suite ........................................ 858 passed (2594 assertions)
    queue ......................... ok — depth 0 · failed 0 · worker running
    flow: publish an article ............................. ok — 72 published
    flow: article on the homepage ......................... ok — 18 sections
    flow: navigation renders in the masthead ..... ok — 14 links in <header>
    flow: homepage order is editable ............ ok — 18 distinct positions
    flow: newsletter stores a subscriber ........... ok — POST route present
    flow: source poll reaches the inbox  ok — 10 active sources · 114 inbox items
    flow: market ticker renders .............................. ok — rendered
    flow: redirect becomes a 301 ............... ok — 0 redirects configured
    public forms ............................... ok — 5 forms, all addressed

No decay. **Day 6 closed.**
