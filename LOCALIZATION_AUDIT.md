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
- **Not audited, with the reason:**
  - `password-reset/reset` needs a mailed token.
  - `multi-factor-authentication/set-up` only renders for a user without 2FA, and every audit user has it by design.

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
