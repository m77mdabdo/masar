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

---

# Task 2.9 — the hero at mobile (2026-10-27)

Base: HEAD (`99d7d16` + Task 2 commits), on the owner's ruling. The zip
`MASAR-HERO-MOBILE-FIX.zip` differed from the pre-Task-2 file by one line and was
discarded. **Lesson: an uncommitted working-tree edit is invisible to git history
and to `masar:decay`, and the next write erases it without trace — commit it or
revert it; do not leave work sitting in the tree.**

Screenshots and data: `tasks/screenshots/task2-9/` — `before/` (HEAD), `after/`;
`top-<w>x<h>.png` (viewport), `hero-<w>x<h>.png`, `mobile.json`. Widths: 375×667,
375×812, 430×932, 768×1024, 1024×768, 1280×800, 1440×900, 1920×1080. **Desktop
did not move:** the 1024, 1280, 1440 and 1920 viewport screenshots are
pixel-identical before and after (0 pixels differ). Every change is scoped below
`lg`, or is invisible (hit area, ARIA, comments).

| # | verdict | measured |
|---|---|---|
| 1 | **CONFIRMED in part; cause refuted** | First question's label bottom vs fold: 375×667 820/667 (153 below), 375×812 820/812 (8 below), 430 and 768 visible. `min-h-[34rem]` is **not** the cause: below lg it sits on a grid holding both blocks (852px of content) and never binds. The hero starts at y=375 because above it are the demo banner (41), topbar (49), masthead (79) and a **180px ticker**. Fix: tighter gaps below lg (kicker gap 24→16 below sm, questions top padding 28→20 below sm, gaps between questions 60→40 below lg). After: 375×812 804/812 **visible**; 375×667 804/667, still 137 below. That cannot be closed inside the hero without removing content: the headline block's own content is ~355px and the hero starts 292px above the fold. The lever is the mobile ticker's height, which is out of scope (ticker redesign forbidden) — **decision for you**. |
| 2 | **CONFIRMED, estimate refuted** | Hero is 852px at 375, not ~1,050. Gaps 60px. After: 796 (375), 770 (430), 738 (768); gaps 40 below lg, 60 at lg+. |
| 3 | **REFUTED** | Below lg, `cover` crops horizontally only — visible source rows 0–100% at 375/430/768 — so the 40% vertical focal point has no effect on mobile. Visible source columns 37.6–62.4% (375), 35.4–64.6% (430); the tower sits at ~55–60% of the source width, so it is in frame. No change. |
| 4 | **CONFIRMED, small** | 1 of 83 demo headlines clamped at 375 with `max-w-[14ch]` (needs five lines); 0 without it. Fix: `lg:max-w-[14ch]`. After: 0 of 83 clamped, max 4 lines. |
| 5 | **REFUTED** | 0 of 83 headlines lose the mint word at 375, before and after. |
| 6 | **REFUTED on HEAD** | Implementation: `<div @class(['relative flex flex-col justify-center p-7 pt-5 text-cream sm:p-9', 'pb-24 sm:pb-24 xl:pb-9' => $count > 1])>` — the explicit `sm:pb-24` restores the clearance `sm:p-9` would reset. Last answer → controls gap: 375 24px, 640 20, 768 20, 1023 20, 1024 21, 1279 70, **1280 40, 1440 60, 1920 60**; no overlap anywhere, including the top end where the padding drops to 36px. The lg/xl split is **deliberate**: at 1024 with 36px the controls covered the third answer (measured in Task 2); from 1280 answers are two lines. Both facts, and that the duplicate `sm:pb-24` is load-bearing, are now in a comment above the element. |
| 7 | **CONFIRMED** | Buttons drawn 36×36. Fix: a 5px `::before` ring, same circle. After: real clicks 21px off centre on all four sides advance the carousel at 375, 768 and 1440 (8/8 each). |
| 8 | **CONFIRMED** | Mean luminance of the photograph under the questions vs under the headline at 375: 0.0059 vs 0.0196 (3.3× darker), spread 0.0043 vs 0.0067. Fix: the lower layer is a 0.22 wash plus a 0.5 strip under the numerals' column only, masked in from the top. After: 0.0086–0.0154 (≈1.5× brighter), spread 0.0049–0.0105. |
| 9 | **REFUTED on HEAD** (all ≥ 4.5) | See the table below. One trap: a byline is a flex paragraph, and measured as a box its full column width reached over unscrimmed photo — that produced a false 4.45 at 768 (and 1.03 at 1440) and briefly justified a tint change, which was reverted once the byline was measured by its own text items. The component's header comment now states the width and slide scope of every figure. |
| 10 | **CONFIRMED, fixed now** | Not a vanishing name: `aria-labelledby` still resolves to slide one's hidden `h1`, so after advancing, the section was named after a headline no longer shown. No live region. Fix (two attributes, so not worth deferring): `aria-labelledby` only when there is one slide — with several, the group's own `aria-label` names it — and `aria-live="polite"` on the headline stack. |
| 11 | **CONFIRMED (inert, not conflicting)** | With `:fill` the ratio class is replaced by `absolute inset-0`; `ratio="hero"` only sets the img's width/height attributes, which `h-full w-full object-cover` overrides. Kept, with a comment at the call site saying the crop is `focal`'s. |

### Contrast at mobile — all three demo slides, worst line box, vs lightest pixel

| width | | headline | standfirst | byline | step label | step body | numerals |
|---|---|---|---|---|---|---|---|
| 375 | HEAD | 7.89 | 5.76 | 4.81 | 14.67 | 14.30 | 4.84 |
| 375 | after | 7.89 | 5.76 | 4.86 | 11.71 | 10.57 | 5.38 |
| 768 | HEAD | 7.86 | 5.77 | 4.78 | 15.08 | 14.12 | 5.01 |
| 768 | after | 7.86 | 5.74 | 4.83 | 11.36 | 10.15 | 5.47 |
| 1440 | both (pixel-identical) | 14.58 | 8.80 | 6.99 | 16.10 | 14.55 | 5.10 |

Step label and body fall (still > 10) because the questions block is lighter (item 8);
numerals rise because the strip sits exactly under them. Recorded in CLAUDE.md §2,
which also now says the earlier "headline ≥ 11.11" was slide one only.

### Files

`resources/views/components/home/big-story.blade.php` — items 1, 2, 4, 6 (comment), 7,
10, 11, header comment (9). `resources/css/app.css` — item 8 (mobile questions layer
only). `public/build/*` rebuilt; every new utility confirmed in the compiled CSS.
`CLAUDE.md` — mobile ratios.

## Where the queue stands

- Task 1.5 items 1–7: **done** (`dd68f49`, `4eb7a38`, `73b6432`, `c647608` + `8d89785`, `02ac68e`, `89af72d`, `391f2bf`).
- Day 7: **done** (`2671a60` … `99d7d16`).
- Task 2: **done**, including 2.9 (`9267962`, `fb6ea10`, `30a216a`, `220381b`, and this commit).
- Deployment: **not started** — blocked on domain, VPS and email provider.

---

# Hero slider — second backdrop, owner's heights, green scrim (2026-10-27)

Owner's rulings (asked before building): the new image is a **second illustrative
backdrop behind slide one**, rotating per page load; the **height targets are a
maximum** with the fold rule kept; the scrim is **dark green with the ≥ 4.5:1 gate
holding**; the carousel **stays manual** (it never had autoplay).

- **The image.** `Riyadh Skyline at Golden Dusk.png` (the text-free one; the file
  named `…From Growth to Profitability.png` is the mockup with text baked in and was
  not used). Both carry an OpenAI `gpt-image` C2PA manifest, so the CLAUDE.md
  generated-image rule applies: layout-owned, `is_illustrative`, credited «صورة
  تعبيرية», alt text naming no city. Shipped as `public/images/home-dusk.jpg`
  (1893×831, 654 KB). Dev database: one media row added (id 649, flagged demo); no
  article or section was touched.
- **Heights.** `clamp(32rem, min(max(32.5rem, 43.75vw), 100svh − 15rem), 52.5rem)`.
  Measured: 527 @1024×768, 560 @1280×800, 630 @1440, 840 @1920 — the backdrops'
  own 2.28:1 at desktop, so they are not cropped. Mobile stays content-height.
- **No height change between slides.** Below lg it changed (375: 796 → 770px):
  each slide was `display:none`. All slides now share one grid cell and hide with
  `visibility`; measured one height per width across all slides and both backdrops.
- **Lighting.** Black → `--color-g-950`; questions column 0.86 → 0.93, mobile wash
  78% → 84%, mobile numeral strip 0.5 → 0.6 — each by measurement. Worst values
  (all slides, both backdrops): mint word 4.73, numerals 4.81; table in CLAUDE.md
  §2. Found on the way: the mint word on the two daylight article slides had been
  at 3.80 on mobile — slide one was all any earlier sweep measured.
- **Honest cost.** Slide one reads like the reference: bright middle, green edges.
  On slides two and three (daylight article photographs) the gate needs the full
  plateau, and the edges read as heavy green bands with a visible fade edge.
- Screenshots: `tasks/screenshots/hero-slider/green-v2/` — every slide, both
  backdrops, all seven widths. `green-v1/` is the first attempt (black's stops in
  green), kept because it shows why the opacities rose.
- Tests: 3 new in `IllustrativeMediaTest` (seeded backdrops name no city; several
  backdrops, one served per load; slides stacked, never `display:none`).
  Suite 961 passed; `masar:decay` green. Backup of the pre-change files:
  scratchpad `backup-hero-2227` (and git, `d5fbcdf`).

## Hero slider — local scrims on the article-photo slides (2026-10-27)

Brief: on slides two and three, remove the lines where the green gradients end,
keep the middle of the photograph clear, darken only behind the text, hold 4.5:1,
change no size, position, content, image or autoplay behaviour. Slide one is out of
scope and untouched.

- **Mechanism.** The section carries `data-ground` (`backdrop` | `photo`), bound by
  Alpine to the visible slide. On `photo` the wide `.hero-scrim` / `.steps-scrim`
  bands switch off. Three local scrims take over: `.text-scrim` behind the headline
  stack, `.questions-scrim` behind the three steps, and `.kicker-scrim::before`.
  Each one is a box in `--color-g-950` with `filter: blur()`, so its edge falls off
  as a Gaussian with no stop at which the slope breaks. Text sits at least 2σ inside
  each box. The scrims are at `z-0` and the text at `z-[1]`: they extend 5rem past
  their block, and in DOM order they had painted over the kicker and the button.
- **How it was measured.** The scrims were rendered over flat grey with the photo
  and text hidden, then CIE L* was read along five rows and five columns. *Slope* is
  the steepest L* change per pixel. *Kink* is the largest change of slope between
  12px windows, which is what the eye sees as a line. *Middle* is the share of the
  grey that survives in the central third.

  | width | kink before → after | slope before → after | middle before → after |
  |---|---|---|---|
  | 375 | 0.28 → 0.22 | 0.28 → 0.45 | 29% → 24% |
  | 768 | 0.28 → 0.19 | 0.28 → 0.45 | 29% → 25% |
  | 1440 | 0.77 → 0.29 | 0.86 → 0.45 | 75% → 76% |
  | 1920 | 0.80 → 0.15 | 0.85 → 0.45 | 63% → 75% |

  The 375 "before" figures in `before/scrim.json` read slope 6.95 / kink 13.91.
  That is an instrument artefact: the last rows of the capture were the page below
  the hero. Columns now stop 16px short, and the corrected before figures are the
  ones shown above.
- **Contrast** (photo slides, worst line box, seven widths): mint word ≥ 5.03,
  numerals ≥ 4.64 (1024, slide three), every other element ≥ 5.43. The full table
  is in CLAUDE.md §2. At 0.93, `.questions-scrim` measured a numeral at 4.49, so it
  went to 0.95.
- **What did not change.** Slide one: 0 pixels differ at 375 / 768 / 1440 / 1920.
  Hero height is one value per width across all slides and both backdrops
  (796 / 770 / 738 / 527 / 560 / 630 / 840). No control overlaps a step, and there
  is no horizontal overflow.
- **Honest limit.** On a phone the text covers nearly the whole stage, so the middle
  cannot stay clear. The scrim there is smoother, not lighter (middle 29% → 24%).
  Two alternatives were tried and both were worse: a tighter mobile blur (slope
  0.83, numerals 4.25) and a lighter 0.80 text scrim (mint 4.13).
- **Noted, not touched.** `.hero-seam` is rendered and has no CSS. It is a dead
  element.
- **A build failure I caused.** The Vite build failed silently because a CSS
  comment contained `L*/px`, and `*/` closed the comment. I caught it only because
  the metrics came back identical to the previous run.
- Screenshots: `tasks/screenshots/hero-photo-scrim/{before,after}/` (after: three
  slides × seven widths) with `scrim.json`. `after-v1/` is the first pass, before
  the 1024 numeral fix.
- Suite 962 passed. `masar:decay`: queue ok, all seven flows ok or SKIP as before,
  6 public forms addressed — no decay found.

## Slimmer chrome, taller hero (2026-10-28)

Brief: a thinner navbar and header, and a clearly taller hero so the Riyadh image
takes more of the screen. Same design, same order, no bar removed, no autoplay,
and no change to the database, articles or images.

- **Chrome** (desktop, measured box including its hairline). Topbar 49 → **37**:
  the row is 36px (`h-9`). Masthead 79 → **65**: the bar is 64px (`h-16`), and
  the mega-menu links match it so the active underline stays on the bottom edge.
  The wordmark keeps its 38px size. Ticker 72 → **62**: the cells went from 54 to
  44px (`h-11`), and the as-of line is unchanged. On mobile the same cells take
  the ticker from 180 to 150px. The drawer opens at 375, 1024 and 1440 with all
  7 links. The menu currently has no mega items, so there was no dropdown to check.
- **Hero.** `clamp(32rem, min(max(36.25rem, 13.5rem + 35vw), 100svh − 11rem), 53.75rem)`.

  | viewport | before | after |
  |---|---|---|
  | 1024×768 | 527 | **580** |
  | 1280×800 | 560 | **624** |
  | 1440×900 | 630 | **720** |
  | 1920×1080 | 840 | **860** |

  - **Why 1280×800 is 624, not ~650.** The fold guard holds it there. At 650 the
    "اقرأ القصة كاملة" button would end below an 800px screen. A taller 1280
    screen gets 660.
  - **Phone and tablet.** The hero stays content-height, as before: 796 / 770 / 738.
  - **The image.** Every slide stays `object-fit: cover`, with no transform and
    no gaps. Because the stage is now taller than the backdrops' 2.28:1, `cover`
    trims the sides rather than the sky. The existing per-image `object-position`
    already frames the tower in full, so none was changed.
- **What did not move.**
  - Headline on the right, questions and gold numerals on the left, read button
    bottom-right, slider controls bottom-left, at every width.
  - One height per width across all three slides.
  - No overlap and no horizontal overflow at 375, 430, 768, 1024, 1280, 1440 or
    1920.
  - The gradients are the same scrims as before; only the stage under them grew.
- **The trade.** At 1440×900 the stage now fills the screen to the bottom edge, so
  the "استكشف مسار" peek below the hero is gone at that size. The read button stays
  on screen at every measured viewport: bottom edge 741 / 785 / 881 / 1021 against
  folds of 768 / 800 / 900 / 1080.
- **Contrast** (re-measured for both backdrops and all three slides, line-box
  method). Every text element is ≥ 4.5:1. The tightest is a numeral at 1024 on a
  photo slide: 4.58, down from 4.64. Both tables in CLAUDE.md §2 now hold the
  2026-10-28 figures.
- **Not touched.** `errors/standalone.blade.php` has its own copy of the 78px bar.
  It is the static error page, which renders outside the layout, and it is out of
  scope here.
- Screenshots: `tasks/screenshots/hero-taller/{before,after}/`, three slides × seven
  widths, viewport captures with the chrome, plus `chrome.json`. Backup of the
  pre-change files: scratchpad `backup-chrome-hero` (and git, `b4a999e`).
- Suite 962 passed. `masar:decay`: queue ok, flows ok or SKIP as before, 6 public
  forms addressed — no decay found.

## Compact header, demo disclosure in the topbar (2026-10-28)

Brief: remove the gold «نسخة تجريبية» band; topbar 30 / masthead 54 / ticker 50 /
stuck header 50; use the space to bring the hero up; keep the hero heights.

- **The band was the demo-mode disclosure, not decoration.** It is one of
  `site.demo_mode`'s five surfaces, and the site is serving invented companies and
  people. Owner's ruling: move it, don't drop it. The band and its 41px are gone.
  The disclosure now takes the topbar's first slot, in place of the descriptor:
  - a gold-ink «نسخة تجريبية» pill, cream on gold-ink, 4.73:1;
  - after it, the full sentence from `sm` up, or «للعرض فقط» on a phone.

  It shows on every page and every width. `DemoModeTest` now asserts the pill, its
  `role="status"`, that no breakpoint hides it, and that it renders before
  `<header>`. Demo mode, noindex, robots and the data are unchanged.
- **Chrome** (measured box including its hairline):
  - Topbar 37 → **31**. The newsletter pill is `min-h-6`, so the target stays 24px.
  - Masthead 65 → **55** at rest. Once the page scrolls past 30px it is
    **51**: a 50px bar plus its hairline.
    - The 4px it loses goes into a margin, so the box in flow never changes
      height: the hero's document top read 136 at scrollY 0, 28, 40 and 600.
    - Menu links stretch to the bar (`h-full`) instead of carrying a fixed
      height.
  - Ticker 62 → **50**: 36px cells with the value at `leading-tight`, plus a 14px
    as-of line, which stays. On mobile the ticker went from 150 to 122.
- **Offsets that depended on the header.**
  - Article rail `top-24` → `top-[4.25rem]`: 68px, 17px under the stuck header.
  - Heading `scroll-margin` and `scroll-mt` 6rem → 4.5rem. A TOC jump lands the
    heading at 92px, under a header that ends at 51.
- **Hero.** It starts at **136px** instead of 205px on desktop, and at 208 instead
  of 319 at 375.
  - The fold guard went from `100svh − 11rem` to `100svh − 7rem` to match the
    shorter stack.
  - The owner's heights hold: 580 / 664 / 720 / 860. 1280×800 is now 664, no
    longer cut to 624.
  - Read-button bottom against the fold: 672/768 · 756/800 · 812/900 · 952/1080.
    At 1440×900 the "استكشف مسار" peek is back.
  - No overlap and no horizontal overflow at the seven widths, and one height per
    width across the three slides.
- **Contrast**, re-measured for both backdrops and all slides: worst 4.58, a numeral
  at 1024 on slide three, unchanged. The 1280 rows in CLAUDE.md §2 are updated.
- Screenshots: before = `tasks/screenshots/hero-taller/after/` (HEAD `cf4ed3e`).
  After = `tasks/screenshots/header-compact/after/`, three slides × seven widths,
  plus a scrolled article at 1440. Backup: git `cf4ed3e`.
- Suite 962 passed. `masar:decay`: no decay found.

## Hero questions column — the photograph back behind the three questions (2026-10-28)

Brief: lighten or remove the dark green behind the three questions only. No boxes,
the same white text, gold numerals and dividers, nothing moved, and the headline's
scrim untouched. A soft text-shadow is allowed. Hold 4.5:1, and if clarity and
contrast conflict, show the comparison.

**They did conflict, so each option was measured before choosing.** Injected CSS,
nothing edited; three slides × 375 / 768 / 1440. "Strict" = text colour against the
lightest pixel under the line box. "Bright" = the share of the photograph's
brightness left behind the column. Worst case over the three slides at 1440:

| option | photograph kept | step text | numerals | verdict |
|---|---|---|---|---|
| before: 0.93 full-height band | 32–51% | ≥ 13.31 | ≥ 4.92 | passes, hides the photo |
| text-shadow only, no field | 100% | 1.2–3.0 | 1.1–1.6 | fails badly on daylight photos |
| strong 5-layer halo only | 100% | 1.4–4.1 | 1.1–1.7 | fails |
| 0.45 blurred field + halo | 76–78% | 3.4–4.9 | 1.3 | fails |
| 0.55 field + halo | 70–73% | ≥ 4.88 (3.62 at 768) | 1.8–2.5 | fails |
| 0.65 field + halo | 64–67% | ≥ 6.48 | 2.5–2.6 | numerals fail even the 3:1 large-text bar |
| 0.75 field + halo | 59–62% | ≥ 7 | ≥ 3.4 | passes WCAG large-text only |
| **split: 0.90 at the numerals → 0.62 under the text, blurred, + halo** | **61–64%** | **≥ 5.96** | **≥ 5.29** | **chosen** |

- **What shipped.** At lg and up, the full-height `.steps-scrim` band is gone on
  every slide.
  - `.questions-scrim` replaces it: a blurred field, dark only where the numerals
    sit and light under the text, so it has no edge and is not a box.
  - The step text carries a close `text-shadow` halo in g-950.
  - The numerals go from `gold/85` to solid `--gold`. They are still gold, and
    still dividered.
  - Below lg it applies to the photo slides. The backdrop slide on a phone keeps
    its existing scrim, which was already the clearest (85–89% kept).
  - The headline's scrim is unchanged.
- **Contrast**, seven widths, both backdrops, three slides: every element ≥ 4.5:1.
  The tightest is the step body at 768 on slide two, 4.56. Both tables in CLAUDE.md
  §2 are updated.
- **Honest limit on phones.** On mobile the questions sit under the headline's
  full-width wash (84%), which item 6 keeps. The photo slides gained less there:
  23–29% → 40–48%.
- **Found on the way and fixed.** The section was `overflow-hidden` while its
  scrims reach past the stage (427px of content in a 375px box on a photo slide).
  A hidden overflow is still a scroll container: at HEAD, `scrollLeft = -40` moved
  the whole slide 40px sideways, and the first after-captures came out shifted. It
  is now `overflow-clip`; the same forcing leaves it at 0. Test added.
- Screenshots: `tasks/screenshots/hero-questions/{before,after}/`, three slides × 375
  / 768 / 1440. `variants/` holds each rejected option on slide two at 1440 with the
  raw measurements.
- Suite 963 passed. `masar:decay`: no decay found.

## Hero — the right-side band goes too; two defects found on the way (2026-10-28)

Owner: «شيل الطبقه اللي ع اليمين كمان» — remove the headline-side layer as well.

- **What changed.** The homepage hero has no band on any slide:
  - `.hero-scrim` and `.steps-scrim` are removed from it, along with the
    `data-ground` switch they needed.
  - Every text block keeps a blurred local field: headline group 0.85, kicker
    0.65, and the questions at 0.90 where the numerals sit, easing to 0.70 under
    the text.
  - `.hero-scrim` still exists for the article hero (`article/hero.blade.php`),
    and its CSS, including the 84% mobile wash, is untouched.
- **Measured before choosing** (headline column; 375 / 768 / 1440; all slides):

  | option | headline column kept at 1440 | weakest element |
  |---|---|---|
  | before: right band (slide one) / 0.86 field (photo slides) | 55% / 70–72% | ≥ 4.98 |
  | text-shadow only, no field | 100% | headline 1.0–1.7 ✗ |
  | 0.65 field + halo | 78% | mint 2.8–3.3 ✗ |
  | 0.75 field + halo | 74–76% | mint 3.8–4.4 ✗ |
  | **shipped: 0.85 field, halo on unclamped lines only** | **71–72%** | **mint 4.90** |

  On a phone, slide one used to sit under an 84% wash over the whole stage. It
  now keeps 30% behind the headline group and 42% behind the questions, and the
  city shows.
- **Defect 1 — I shipped it in `41df162`: the text halo was cut into rectangles.**
  - The headline (`line-clamp-4`), the standfirst and every answer (`line-clamp-3`)
    clip their own box, so their `text-shadow` rendered as hard-edged dark boxes
    behind the text. That is exactly the "no boxes" the brief ruled out.
  - The contrast sweep could not see it, because a clipped halo still darkens the
    ground. The gradient-smoothness measure read 2.1–2.75 because it counts the
    halos; it was seen at 2× zoom.
  - Fix: clamped text carries no halo, and the fields are set for that (0.85,
    0.70). A checker now walks every shadowed text node in the hero for a
    clipping ancestor: 40 at 375 and 40 at 1440, none clipped. A test pins the
    CSS exemption and the clamp spelling it relies on.
- **Defect 2 — I shipped it in `7e32a66`: the page jumped 4px when the header
  tightened.**
  - The stuck header's 4px margin applied instantly, while its height animated
    over 200ms. For the whole transition the page sat 4px low, then crept back.
  - Sampled every frame: the hero's document top went 212 → 208, and 204 → 208
    on the way back, for 0.0040 layout shift per toggle. My earlier check read
    positions 350ms later, after it had settled.
  - Fix: the margin animates with the same duration and curve. Now 208 / 207.98
    over 44 frames, layout shift 0.0000.
- **Contrast**, seven widths, both backdrops, three slides: every element ≥ 4.90.
  Both tables in CLAUDE.md §2 are replaced.
- **Smoothness**, shadows excluded: kink 0.15–0.18 at every width.
  - At 375 a 2.0 reading turned out to be the header jump in defect 2, caught by
    the first capture.
  - Heights, positions, overlap and overflow are unchanged at all seven widths.
- Screenshots: `tasks/screenshots/hero-no-bands/{before,after}/`, three slides ×
  375 / 768 / 1440, plus a 2× crop showing the clamp-halo fix.
- Suite 964 passed. `masar:decay`: no decay found.
