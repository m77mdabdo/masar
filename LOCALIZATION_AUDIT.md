# MASAR — localization audit

Date: 2026-10-29. Every figure below was measured on dev: `127.0.0.1:8000` for the public site,
and an isolated copy for the admin (see Method). Machine-assisted translation, published on the
owner's authorisation; **no native-speaker review has been recorded in any edition.**

## Coverage matrix (measured)

✓ means checked and passing, with the evidence in the column on the right.

| Component | AR | EN | FR | HI | UR | ZH | Evidence |
|---|---|---|---|---|---|---|---|
| Public website | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Browser audit: 2,346 checks, 0 defects. HTTP crawl: 825 pages, 0 leaks. |
| Admin dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Browser audit: 52 URLs × 17 viewports per language, 0 defects in the final runs. |
| Authentication | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Sign-in, 2FA challenge and password-reset request, per language (browser and test). |
| Forms | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Every create/edit page, including file uploads (FilePond labels). |
| Validation | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Rendered server errors read back in the browser in all six; Livewire locale test. |
| Notifications | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | Recipient's language whatever the worker's (test); 1,058 Filament keys resolve. |
| Emails | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | `lang/*/mail.php` and framework JSON; `SourceDisabled` subject sent in French from an Arabic worker (test). |
| Database content | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | `masar:translations:coverage`: 83/83 stories, 71/71 entities, 19/19 opportunities, alt text complete. |
| SEO | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | `<head>` and JSON-LD crawl: 133 pages × 4 non-RTL editions, 0 Arabic. hreflang, canonical and sitemap tests. |
| Error pages | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | 404 route audited in every edition at 17 viewports. |

AR is the source edition. For UR, "no leak" means no Arabic *interface* string was found. Urdu
shares the script, so the check matches against the Arabic catalogue rather than the alphabet.

## Method

- **Public site:**
  - `scratchpad/audit-final.js` runs 23 route types × 6 editions × 17 viewports.
  - Viewports: 320, 360, 375, 390, 414, 480, 576, 768, 820, 1024, 1280, 1440, 1920, 2560, and landscape 667×375, 844×390 and 1024×768.
  - Checks: horizontal scroll, elements escaping the viewport, raw keys, broken images, console and page errors, `lang`/`dir`, and Arabic outside lang-marked elements.
  - An HTTP crawl covers every published story, section, topic, company, person, author and opportunity in the five non-Arabic editions, plus a `<head>`/JSON-LD crawl.
- **Admin:**
  - **Environment:** a scratch database restored from the newest verified dump, served on `:8010` with its own cache, Redis and session prefixes. Dev data and dev sessions were never touched.
  - **Sign-in:** a throwaway super admin per language, with 2FA, signs in through the real login and TOTP challenge.
  - **Routes:** every admin GET route on the router, the first record per resource for `{record}`.
  - **Checks:** the public set, plus interface strings left in Arabic or English. Those are matched against the admin catalogue and every Filament string, so Arabic *data* (article titles, user names) is told apart from untranslated *labels*.
  - **Interactions:** empty-form validation, the table filters panel, and the user menu with its language switcher.
- **Screenshots** at 375 and 1440 for six representative admin pages, plus all public routes. They were inspected by eye and then deleted (488 files).

## Routes

**Public: 23 route types, in all six editions.** Home, section, article, topic, company,
person, author, search, markets, video, opportunities, opportunity, newsletter, about, contact,
team, advertise, corrections, editorial standards, privacy, terms, cookies and 404. The HTTP
crawl adds every individual record page: 165 per non-Arabic edition, 825 in total.

**Admin: 54 GET routes; 52 audited in all six languages.**
- **Dashboard and pages:** dashboard, pipeline, homepage composer, homepage preview, navigation, settings, 404 log, profile.
- **Resources (list, create, edit):** articles, categories, companies, countries, industries, markets, opportunities, people, redirects, roles, sources, topics, users.
- **List only:** audit log, intelligence inbox, subscribers.
- **Guest:** login, password-reset request.
- **`password-reset/reset` and `multi-factor-authentication/set-up`** were not in the route sweep (one needs a mailed token, the other a user without 2FA). Both were then audited end to end in their own flows, in all six languages at 17 viewports. See "Password reset and 2FA enrollment" below.

## Translation resources

| Resource | Per edition | Notes |
|---|---|---|
| `lang/{code}/{site,home,article,pages,errors,enums,entity,mail,admin}.php` | 559 keys | Generated from one master; parity-tested. |
| `lang/{code}.json` | 5 | Laravel's mail template strings. |
| `lang/{code}/{auth,pagination,passwords,validation}.php` | 146 | Laravel ships English only. Before this pass, five editions rendered `validation.required`. |
| `lang/admin/{code}.json` | 596 | Admin panel text, keyed by the Arabic source. |
| `lang/vendor/{namespace}/{ar,fr,hi,ur,zh_CN}/…` | hi 786 · ur 335 · zh_CN 222 · fr 128 · ar 3 | Only the Filament keys each package lacks. 1,058 keys resolve in every edition with fallback off. |
| `database/seeders/data/content/{code}*.json` | 742 | Database content dictionaries, keyed by sha1 of the Arabic. |

Owner publication approval is recorded for **247 files** at their final fingerprints. Native
review: **0 strings** in every edition.

## Translated database models and content types

| Model / content | How | Coverage |
|---|---|---|
| Article (title, standfirst, the four questions, summary, body, blocks, key figures, alt, SEO, citations, sponsor) | One row per edition in the translation group, `owner_authorized` | 83/83 published × 5 |
| Category, Topic, Company, Person, Industry, Country, Market | `*_translations` rows | 71/71 × 5 |
| Opportunity | One row per edition | 19/19 × 5 |
| Media alt text and credit | `alt_translations` / `credit_translations` on the record | complete |
| Bylines | `users.name_translations` | complete |
| Market labels and values | `market.translations` setting | complete |
| Settings (tagline, descriptor, SEO description), homepage section titles, menus | Per-edition settings and labels | complete |

## Defects found and fixed in this pass

| Defect | Where | Fix |
|---|---|---|
| Raw `validation.required` (and pagination, auth and password keys) in 5 editions, including the Arabic admin | Every form | Framework files in all six. |
| Admin text was Arabic-only: 798 literals | Panel, actions, exceptions, notifications, 9 views | Wrapped in `__()`. Concatenations became whole sentences with placeholders; class-level labels became getters; boot-time labels became closures. |
| An English editor's validation errors and action notices came back in Arabic | Every Livewire update | `SetAdminLocale` made persistent middleware. |
| Login page had no language | `/admin/login` | `?lang=` picker with session memory; falls back to the public edition cookie. |
| Mail in the worker's locale | Notifications | `User::preferredLocale()`. |
| 786 Hindi, 335 Urdu, 222 Chinese and 128 French Filament keys missing, falling back to Arabic | Filament UI | `lang/vendor` gap files. |
| FilePond showed English "Remove" and "Cancel" in Hindi, Urdu and Chinese | Every file upload | Labels supplied as `data-*` attributes from our translations. |
| **Companies, opportunities and people pages returned 500 on real data, in every language including Arabic** (pre-existing) | Admin | Closure parameters renamed to Filament's injected names (`$record`, `$state`). The guard test was widened to every Filament-evaluated method. |
| Homepage preview fixed in Arabic; its inline `<style>` blocked by CSP (pre-existing) | `/admin/homepage/preview/{layout}` | Locale middleware on the route; previews the editor's edition; CSS moved to `public/css/homepage-preview.css`. CSP is unchanged. |
| Arabic wordmark announced in every language | Admin brand | MASAR outside the Arabic panel. |
| Native language names unmarked in translation forms; `config()` read directly | `TranslationsField` | Language names in the panel's language; reads through `Locales`. |
| Urdu rendered in a naskh fallback with broken joins; Nastaliq descenders cropped by truncation | Admin theme | Per-script faces (Nastaliq, Devanagari, Han) on the variables Filament reads; taller line box for clipped Urdu labels. |
| Audit log showed raw `created`/`updated` and Arabic transitions with the arrow reversed | Audit log | `AuditDescription` presents the stored entry in the panel language; the record itself is unchanged. |
| Eastern Arabic digits in two admin strings | Sources, settings | Western digits. |

## Remaining exceptions, with justification

- **Arabic by design, as data or stored records:**
  - the transliteration and folding tables;
  - the Urdu-letter guard;
  - the importance scorer's rationale, stored on the item at ingest with its figures;
  - audit descriptions, which are evidence and stay as stored (presented localized);
  - console output;
  - a draft's Arabic placeholder title;
  - the «مسار» lockup in the Arabic panel.

  Each is listed with its reason in `AdminLocalizationTest`.
- **User data is shown as entered:** article titles of other editions in the shared admin lists, user names, and source names.
- **Admin `<html lang>` is `ur-pk` / `zh-hans`, not `ur-PK` / `zh-Hans`.** Filament writes the app locale. Both forms are valid BCP 47, which is case-insensitive.
- **The French word "Active", the Urdu «قسم» (type) and the Urdu «اعتماد» (trust)** matched the leak dictionaries. They are correct words in those languages.

## Translation quality (automated checks; not a native review)

- **Placeholders:** the multiset of `:name` and `%d` tokens is preserved in every admin and framework string, checked by tests and by the installers.
- **Pluralisation:** segment counts are preserved.
- **Script:** no Arabic in the Latin, Devanagari or Han editions. Urdu values differ from the Arabic source, except the «، » separator.
- **Digits:** Western digits only.
- **Content dictionaries:** keys complete, digits and Latin tokens preserved.
- **Not verified by a person:**
  - The idiom of all five translated editions.
  - Transliterated proper nouns: companies, people, sponsors.
  - Chinese riyal amounts, which read «40 十亿里亚尔» rather than «400亿».
  - French and Hindi gender agreement in templated headlines.

## Accessibility findings

- The sign-in language picker gives each language its own `lang`/`dir` and a 24px minimum target.
- The admin wordmark's accessible name follows the panel language.
- File-upload buttons are announced in the panel's language.
- No new target under 24×24 was introduced. axe was not re-run in this pass.

## Tests

- **`php artisan test`:** 1,152 passed.
- **Localization tests** (`tests/Feature/I18n`, `tests/Feature/Translation`): all pass.
- **`php artisan masar:decay`:** green.
- **The `name_translations` migration:** forward, rollback and re-migrate verified. No new migrations were added in this pass.

## Release-readiness pass (2026-10-29, after `28a0499`)

### Chinese amounts

Chinese reckons large amounts in 万 (10⁴) and 亿 (10⁸). The machine pass had written «40 十亿里亚尔» and «300 百万里亚尔».
- **Dictionaries:** 50 strings regrouped (40 十亿 → 400亿, 300 百万 → 3亿, 18 百万 → 1800万), using exact decimal arithmetic. Each conversion is asserted to keep every amount's value and currency.
- **Dev database:** 49 Chinese stories and the market labels corrected through `zh-hans-corrections.json`. A stored segment is replaced only while it is still exactly the old machine text; a test proves a person's edit survives.
- **Result:** 0 of 83 Chinese stories still use 十亿/百万. The Chinese public edition was re-audited: 391 checks, 0 defects.

### Transliterated names

Every company, person and sponsor in the product is demo fiction (`is_demo = true`, website `*.example`; sponsors and citation publishers are labelled «تخيّلي»). There is therefore no external authority to check against. The authoritative record is the project's own: the Arabic name and the editorially chosen Latin slug (from the demo cast, 2026-10-27).

| entity (Arabic) | slug | EN/FR match slug | same spelling in every story, per edition (en · fr · hi · ur · zh) |
|---|---|---|---|
| زيزفون الرمال للطاقة المتجددة | zayzafoon-sands | ✓ ✓ | 6/6 in all five |
| دُرّاق للمدفوعات | durraq-payments | ✓ ✓ | 2/2 in all five |
| صدفة القلزم للشحن البحري | sadafat-al-qulzum | ✓ ✓ | 2/2 in all five |
| دار القمرية للضيافة التراثية | dar-al-qamariya | ✓ ✓ | 1/1 in all five |
| شعاع النفود للطاقة | shuaa-al-nafud | ✓ ✓ | 2/2 in all five |
| قطميرة للأغذية | qitmira-foods | ✓ ✓ | 1/1 in all five |
| عرصات · رُقيم · أثلة · مرقاة | arasat-development · ruqaym-data · athla-agritech · mirqah-learning | ✓ ✓ | not named in any story |
| 8 people (لمى الحربي … سعد المطيري) | lama-al-harbi … saad-al-mutairi | ✓ ✓ | not named in story text |

**Needing human confirmation.** No record exists for these, so nothing was changed by guesswork:
- **Every Hindi, Urdu and Chinese spelling** of the 10 companies, 8 people and 12 sponsor names. Only the Latin slug exists as a record.
- **Two specific readings:**
  - Hindi «तुर्की अल-सुबैई» (Turki): «तुर्की» is also the Hindi word for Turkey/Turkish.
  - Chinese «扎伊扎丰沙地…» translates "Sands" (沙地) where the Latin name keeps it as part of the name.
- **The 12 sponsor names**, which have no slug at all, in all five editions.

### Password reset and 2FA enrollment, in a real browser

Both flows were run end to end in every language, on an isolated copy with a local SMTP sink, so no mail left the machine.
- **Password reset:** request in the visitor's language → mail delivered → real token link → new password → sign-in with the new password and 2FA → `/admin`.
- **2FA enrollment:** a fresh editor-in-chief without 2FA is forced to `/admin/multi-factor-authentication/set-up` → authenticator modal → code from the shown secret → recovery codes → `/admin`. 2FA was confirmed saved for all six users.

Every page of both flows was checked at the 17 viewports for language, direction, sideways scroll, off-screen controls and leaked strings.

| | ar | en | fr | hi | ur | zh |
|---|---|---|---|---|---|---|
| reset request / new-password pages (17 widths each) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| reset mail in the user's language | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| signed in with the new password + 2FA | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| forced to enrol; set-up page, modal, recovery codes (17 widths each) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| enrolled user reaches the panel | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

**Defect found and fixed:** the reset mail was in English in every edition, Arabic included. Filament sends it with Laravel's own strings, which are keyed by their English text, and `lang/{code}.json` held none of them. The five sentences are now in all six editions, with a test that sends the mail per edition.

### Backups

`deploy/backup.sh` now:
- writes `.partial` files and renames them only when complete;
- checks free space before each write, keeping `MASAR_BACKUP_MIN_FREE_MB` (default 1024) spare;
- skips media when short of space, rather than the database, unless media is required;
- reuses the newest media archive while the media fingerprint is unchanged;
- prunes complete sets only, never the newest, and a media archive only when nothing references it.

Each behaviour was exercised against a scratch destination:

| case | result |
|---|---|
| unchanged media | reused, exit 0 |
| room for the dump, not the media | media skipped, exit 0 |
| same, with media required | refused, exit 1 |
| no room for the dump | refused before writing |
| room for both | new archive |
| dump fails mid-way | no partial left |
| retention | aged sets and their unreferenced archive removed; a still-referenced archive kept |

No existing backup was touched.

## Homepage and structural parity (2026-10-30, release-blocking fix)

### Why the hero was missing outside Arabic

The big story (the cinematic hero) is a homepage section of type `big_story` with `source: manual`. An editor pinned three stories by id: `config.article_ids = [198, 197, 137]`. **Those ids are the Arabic rows.** `ComposeHomepage::buildPool()` fetched the pinned ids *within the requested edition* (`forLocale($locale)->whereIn('id', …)`).
- In English, French, Hindi, Urdu and Chinese the three ids are not rows of that edition, so nothing matched.
- A `manual` section stops when its pins are exhausted ("an editor who picked three stories meant three"), so the section resolved empty and the template drew nothing.
- The page began at Explore MASAR.
- The `issue` section (also `manual`) vanished the same way.
- The `leads` and `video` sections (`mixed`) silently lost the editor's picks and were filled automatically. In some editions that put sponsored stories in the leads row that the Arabic page does not show.

No test caught it. Every string that did render was translated, so the translation checks passed. Nothing asserted that the hero existed.

### The fix

- **Pins resolve to the same story in each edition.** `ComposeHomepage::pinnedIn()` resolves each pinned id to its translation group, then to that edition's published row, in the editor's order. An edition without a published translation leaves that pin empty, never another edition's text.
- **A stable tie-break.** `PublishedArticlesQuery::latest()` and new `newestFirst()` scopes on `Article` and `Opportunity` break equal publication times on the original story's id (`COALESCE(source_article_id, id)`). Breaking on a row's own id had ordered same-moment stories differently per edition, so automatic sections drew different stories, and `reports` had 5 items in Arabic and 4 elsewhere.
- **Opportunity of the week** now picks this edition's published, open opportunity. It had taken the newest row of any edition, closed or draft.

### Further structural defects found by comparing 810 pages against Arabic, all fixed

| defect | cause | fix |
|---|---|---|
| Translated stories lost their inline images and video, and the video page had no player outside Arabic | Blocks keep their `media_id`, but the media is attached to the original row; translations looked in their own collections | `Article::mediaOwner()`: translations read the original's media |
| Entity pages outside Arabic lacked opportunities and ordered items differently | Opportunity translations were imported without mentions; translated mentions kept the import time instead of `published_at` | Seeder `mentions()` stage, idempotent; `masar:recount` |
| Hindi search matched nearly every story | The normaliser split words on combining marks, so «ज़ैज़फ़ून» became four single letters | `\p{M}` stays inside words |
| French and English hero headlines cut at four lines | `line-clamp-4` at `14ch` | five lines, a smaller Latin size on phones; the scrim follows the block |
| Urdu hero lines overlapped | Tailwind's `leading-[1.2]` overrode the Nastaliq base leading | an unlayered Nastaliq heading rule |
| The French opportunity title ran over the photograph; French market labels truncated | 1.75rem at `22ch`; `truncate` | sized by length with a five-line clamp; labels wrap |
| Topbar links wrapped at tablet width in all six (Arabic too) | no `nowrap` | `whitespace-nowrap`; no overflow from 640 to 1280 |
| «Newsletter» on French pages | my earlier shortening of the label | «Lettre d'info» |
| **Admin avatars were loaded from ui-avatars.com with each staff name** | Filament's default avatar provider | local `InitialsAvatarProvider` (inline SVG). Admin pages now make no third-party requests |

### Section matrix (browser, final code)

| Homepage section | AR | EN | FR | HI | UR | ZH |
|---|---|---|---|---|---|---|
| Cinematic hero, above Explore MASAR, 720px tall at 1440 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Featured story, localized, linking into its own edition | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Three editorial questions per slide | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Slideshow: auto-advance, pause/play, previous, next, keyboard, thumbnail | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Reduced motion: still, with no pause control | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Market ticker | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Explore MASAR | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| News sections (3), same stories, same order | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Sponsored content | none on the homepage in any edition (the pinned leads are not sponsored), identical | | | | | |
| Footer | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Same 15 sections in the same order | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| No defect at 13 widths (320–2560 and landscape) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Composition: all 18 sections are identical to Arabic in type, item count and stories, in every edition.

### Verification on the final code

- **Public responsive audit:** 2,346 checks, 0 defects.
- **Leak crawl:** 825 pages, 0 Arabic.
- **`<head>` crawl:** 133 pages × 4 editions, 0.
- **Structure crawl:** 810 pages against Arabic; the only differences are the four searches for the Latin letter "a", which legitimately match different text.
- **Same-name search:** 4 results in every edition.
- **Truncation:** no hero headline truncated at 16 widths in any edition; no opportunity title clipped and no pulse label overflowing at 5 widths.
- **Screenshots:** viewport slices at 1440, 768 and 375 of all six homepages (12–16 slices each) were inspected; full-page capture blanks `content-visibility` sections. All were deleted after inspection.
- **Admin:** dashboard, articles, homepage composer and preview, pipeline, settings and users re-audited in six languages at 17 viewports on an isolated copy.

### Regression tests

- **`tests/Feature/Public/HomepageParityTest.php`:**
  - pins resolve per edition, in the editor's order;
  - a missing translation leaves a pin empty, with no fallback;
  - every edition draws the hero, with the same section sequence as Arabic;
  - equal-time stories order identically;
  - the opportunity of the week is per edition.

  With the old pin lookup restored, the three hero tests fail.
- **`tests/Feature/Public/StructureParityTest.php`:** translated stories show the original's inline images; translated opportunities carry their mentions; Hindi search folds whole words.
- **`AdminLocalizationTest`:** avatars are drawn locally.
