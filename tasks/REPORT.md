# Task 2 — homepage visual refinement (2026-10-27)

Same design, same image, same distribution, same tokens and fonts. Everything below
was measured in a real browser — Playwright 1.64.1 headless Chromium, run from the
npx cache, nothing added to `package.json` — against `php artisan serve` on
`127.0.0.1:8000`. Seven widths: 375×812 · 430×932 · 768×1024 · 1024×768 · 1280×800 ·
1440×900 · 1920×1080.

**Screenshots:** `tasks/screenshots/task2/` — `before/` (pre-change code, first run),
`before-recheck/` (pre-change code re-measured with the corrected contrast
instrument), `after/`. Each holds `home-<width>.png` (viewport), `hero-<width>.png`
(the big story) and `measurements.json` (every number in this report).

---

## 2.1 What was measured, and the defects it found

Measured and **found sound**, so not touched: topbar 49px and masthead 79px at every
width; Latin wordmark 38px in a 78px bar (49%), Arabic wordmark 24px, baselines
aligned; nav links 12–12.5px semibold with even 16px (1280) / 18px (≥1440) gaps,
burger centred on the bar (129.5 vs 129.5); search target 24×24; container padding
20/30px; **zero horizontal overflow at all seven widths** before and after; no
physical-direction utilities (`ml-`/`mr-`/`pl-`/`pr-`) in any homepage component.

| # | defect | measured before | after |
|---|---|---|---|
| D1 | ticker labels truncated — **introduced by me in Day 7**, long fictional labels in cells sized for «تاسي» | «المؤشر التجريبي» → «ال…» at 1024 | short labels; no truncation |
| D2 | headline ink clipped by `line-clamp` (leading 1.1) | last-line descenders visibly cut at every width; overflow 2–4px | leading 1.2 + clamp padding; overflow 0 |
| D3 | headline vs lightest pixel behind it | white 4.45 @1440, 2.07 @1920; mint word 3.77 @1024 … 1.0 @1920 | white ≥ 11.11, mint ≥ 5.74 |
| D4 | numerals 01/02/03 vs lightest pixel | 1.31 – 4.69 (≥1024), 3.82 – 5.54 (<1024) | ≥ 5.07 |
| D5 | hard vertical scrim seam at the container edge | x=70 @1440, x=310 @1920 | soft fade, no seam |
| D6 | hard horizontal seam atop the stacked questions | visible @375/430/768 | fades in from the top |
| D7 | carousel controls cover question 3 | overlap @375/430/768 (and @1024 once D9 landed) | none |
| D8 | kicker touching the headline (<lg) | −1px | 18px |
| D9 | hero runs past a 768px fold | CTA bottom 797 at 1024×768 | 725 |
| D10 | answers ellipsized at 1024 | 3 of 3 cut at two lines | 0 of 3 |
| D11 | backdrop upscaled below lg (400w/900w file stretched to ~1,333px) | 3.3× at 375 | **not fixed** — see below |
| D12 | crushed shadows in the photograph | — | contrast(0.92) brightness(1.06) on the image layer only |
| D13 | ticker prints overflow their cells, lg–2xl (pre-existing) | text box 15px @1024, prints across the sparkline; «11,234.56» clipped 3px @1280 | none |

### Geometry, before → after

| width | hero height | CTA bottom / fold | kicker → headline | controls over Q3 | answers ellipsized | headline ink overflow |
|---|---|---|---|---|---|---|
| 375 | 750 → 852 | 713 → 747 / 812 | −1 → 18px | yes → no | 0/3 → 0/3 | 2px → 0 |
| 430 | 724 → 826 | 687 → 721 / 932 | −1 → 18px | yes → no | 0/3 → 0/3 | 2px → 0 |
| 768 | 731 → 828 | 650 → 686 / 1024 | 0 → 18px | yes → no | 0/3 → 0/3 | 3px → 0 |
| 1024 | 600 → 528 | 797 → 725 / 768 | 227 → 139px | no → no | 3/3 → 0/3 | 2px → 0 |
| 1280 | 600 → 560 | 797 → 757 / 800 | 198 → 139px | no → no | 0/3 → 0/3 | 3px → 0 |
| 1440 | 600 → 600 | 797 → 797 / 900 | 181 → 159px | no → no | 0/3 → 0/3 | 3px → 0 |
| 1920 | 600 → 600 | 797 → 797 / 1080 | 181 → 159px | no → no | 0/3 → 0/3 | 4px → 0 |

The mobile hero is ~100px taller: that is the room reserved under the questions for
the carousel controls (D7). At ≥1024 the kicker gap shrinks because the headline is
taller (D2) and the stage is `justify-between`.

## 2.4 Contrast — the hard gate

Method: render the page, record each element's line boxes, re-render with all hero
text made transparent, and compare the element's own colour (alpha composited)
against the **darkest and the lightest** pixel inside its boxes. Ratios are
darkest / lightest.

| width | headline | mint word | labels (min) | numerals 01 · 02 · 03 (vs lightest) |
|---|---|---|---|---|
| | before → after | before → after | before → after | before → after |
| 375 | 15.02/12.06 → 14.55/12.00 | 6.75 → 6.59 | 18.65 → 16.44 | 4.32 · 4.50 · 5.54 → 5.07 · 5.80 · 5.64 |
| 430 | 14.86/11.97 → 14.37/11.64 | 6.40 → 6.24 | **1.14** → 16.67 | 4.10 · 3.82 · 4.93 → 5.20 · 5.26 · 5.48 |
| 768 | 14.72/11.60 → 14.37/11.11 | 5.90 → 5.74 | **1.14** → 15.96 | 4.84 · 4.70 · 4.54 → 5.50 · 5.85 · 5.84 |
| 1024 | 18.38/7.81 → 18.49/15.06 | **3.77** → 7.72 | 15.51 → 18.97 | 4.59 · **3.64** · **1.90** → 6.16 · 6.05 · 5.39 |
| 1280 | 18.25/8.04 → 18.30/15.40 | **3.89** → 7.73 | 17.68 → 19.74 | 4.69 · **3.44** · **1.48** → 6.16 · 6.05 · 5.28 |
| 1440 | 18.17/**4.45** → 18.34/15.06 | **2.15** → 7.38 | 18.27 → 19.61 | 4.55 · **3.08** · **1.31** → 6.19 · 6.01 · 5.51 |
| 1920 | 17.38/**2.07** → 18.25/14.38 | **1.00** → 6.95 | 17.23 → 19.43 | 4.55 · **2.52** · **1.83** → 6.16 · 5.88 · 5.75 |

**Every after-value is ≥ 4.5; the lowest anywhere is 5.07.** The 1.14 label readings
before were the carousel controls lying on the third label (D7). These figures are
now in CLAUDE.md §2 and in the component's `contrast-safe` note.

Two instrument faults were found and fixed on the way, and are recorded because they
produced plausible numbers: computed colours came back as `oklab()` and were parsed
as RGB (numerals read ~1.1:1 regardless), and a scrolled page put the sticky cream
masthead over the headline, so the "lightest pixel" was the masthead (1.1:1 at 375).
The before set was re-measured with the corrected instrument (`before-recheck/`).

## Lighting, in words

The scrim used to be sized in percentages of a full-bleed section while the text sits
in a centred 1300px column — so on a wide screen the headline walked out of it and
over the sunset. It is now sized in lengths from the text: dark to the far edge of
the headline (or standfirst, whichever is wider), then an 8rem fade, then the
photograph untouched. The questions column is evenly dark from its own edge (the
numerals sat on its weakest stop), feathers in over the 4rem before it, and fades out
up to 6rem past the container instead of ending in a seam. A first attempt ran the
questions band dark to the viewport edge and used a 13rem headline fade: it passed
the gate but blacked out most of the skyline and left a bright vertical slot between
the two scrims — visible in the screenshots, so it was undone. The image layer (which
holds no text) gets `contrast(0.92) brightness(1.06)`, lifting the unlit side of the
skyline out of flat black.

## D11 — not fixed, by measurement

The backdrop is portrait-cropped below lg, so `sizes="100vw"` fetches a file far
narrower than it is displayed. The fix (`(max-width: 1023px) 900px, 100vw`) was
built and measured on Lighthouse mobile through the compression proxy: it pulls the
1800w file (174 KB vs 60 KB) and moved homepage LCP from **3,086 / 3,242 / 3,095 ms to
4,057 / 4,056 / 4,130 ms**. Reverted. The real fix is an art-directed portrait
conversion for the stacked layout — media-pipeline work, outside this task. With the
other Task 2 changes in place, homepage LCP is 3,086–3,242 ms against this morning's
3,086 / 3,089 on the same harness: no regression.

## One judgement call to check

D13 hides the ticker sparkline between lg and xl (1024–1279). The brief forbids
removing elements; the sparkline was already hidden below sm for the same reason —
there is no room for it beside the figure — and with it shown at 1024 the figures
were drawn across their own line. If you would rather keep it, the alternative is
narrower figures or fewer cells at that width, which is a ticker redesign.

## Files changed, each traced to a defect

| file | change | defects |
|---|---|---|
| `resources/views/components/home/big-story.blade.php` | `hero-photo` class on the image layer; viewport-aware lg height; `gap-6` on the text column; headline leading 1.2 + clamp padding; controls clearance `pb-24` below xl; answers `line-clamp-3` below xl; steps scrim insets moved to CSS; updated contrast note | D2 D7 D8 D9 D10 D12, D4–D6 |
| `resources/css/app.css` | `.hero-scrim` length-based stops; `.steps-scrim` insets, feather and bleed; vertical steps scrim below lg; `.hero-photo` filter | D3 D4 D5 D6 D12 |
| `resources/views/components/layout/ticker.blade.php` | sparkline hidden lg–xl; cell padding 16px lg–2xl | D13 |
| `database/seeders/MarketFiguresSeeder.php` (+ dev settings) | short sample labels | D1 |
| `public/build/*` | rebuilt; every new utility confirmed present in the compiled CSS | — |
| `CLAUDE.md` | hero contrast ratios added beside the token ratios | 2.4 |

No new component, no `!important`, no colour or token change, no font change.

---

## Suite and decay — end of the whole run

`php artisan test > /tmp/suite6.txt 2>&1` — **958 passed, 3,610 assertions, 0 failed,
0 skipped.**

`php artisan masar:decay` — exit 0:

| check | state | detail |
|---|---|---|
| suite | MANUAL | run `php artisan test` and record it in REPORT.md |
| queue | ok | depth 0 · failed 0 · worker running |
| migrate:fresh --seed | SKIP | not run — use --with-seed (~15 min) |
| flow: published article is served | ok | renders with its headline |
| flow: homepage links published articles | ok | 40 of the latest 50 linked from /ar |
| flow: navigation renders in the masthead | ok | 14 links in `<header>`, 23 menu items |
| flow: homepage sections have distinct positions | ok | 18 for 18 |
| flow: newsletter form posts to the subscribe route | ok | |
| flow: source polling succeeds | SKIP | polling disabled — none polled |
| flow: market ticker renders | ok | rendered |
| flow: configured redirects answer | SKIP | 0 redirects configured |
| public forms | ok | 6 forms, all addressed |

`--with-seed` was deliberately not run: the scratch seed writes media files to the
same local disk as the dev database under colliding ids, and would overwrite dev's
photographs. That hazard is worth a fix of its own (a scratch `MEDIA_DISK`) before
anyone runs that check again.
