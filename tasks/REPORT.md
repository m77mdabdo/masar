# Finish pass: ticker, big story, tiles, masthead

Opened at 1440px beside `resources/prototype/home.html` and went cell by cell
before writing this. Every figure below is measured off the rendered page, not
read off the stylesheet.

---

## 1. Ticker

### The sparkline is now data

The shape points are a new `series` field on every `market.ticker` row —
comma-separated numbers an editor types in الأسواق, alongside the label, value
and change that were already there. `MarketFigures::ticker()` parses them the
same way the index chart already parsed its own.

No points, no chart. A row saved without them renders the label, the value and
the change, and nothing where the line would be. That is tested as a mechanism,
not as a picture: two rows in, one with points and one without, exactly one
`<polyline>` comes out.

Seeded shapes, direction agreeing with the change in every row:

| | change | shape |
|---|---|---|
| تاسي | +1.24% | rising |
| نمو | +0.63% | rising |
| برنت | −0.31% | falling |
| الذهب | +0.82% | rising |
| دولار/ريال | 0.00% | flat |

**I changed one of your demo figures.** نمو was seeded at −0.41% and you asked
for three risers, one faller and one flat. A rising line above a negative print
is a contradiction a reader sees before they read either half, so نمو is now
+0.63% in the ticker *and* in the instrument grid, where the same instrument
also appears. Change it back in الأسواق and the line follows the number.

Colour follows direction: mint riser, coral faller, grey flat. The prototype
draws Gold's line in gold; your rule says colour is direction, so Gold is mint.

**One fix inside the sparkline.** A flat series has no span to scale against.
The old code fell back to a divisor of 1, which pinned every point to the floor
of the box — the pegged riyal was drawn as a collapse. It now centres. Pinned
by a test.

### Measured against the prototype

| | prototype | rendered |
|---|---|---|
| band height | 54px | 54px |
| cell divider | `rgba(255,255,255,.07)` | same, on `border-inline-start` |
| label | 8.5px, .12em, mint-2 | 8.48px, 1.0176px (= .12em), `rgb(168,212,188)` |
| value | 14px white | 14px `rgb(255,255,255)` |
| change | 11.5px, ▲/▼ before | 11.52px, glyph before |
| pulse cell | `g-850`, 10px | `rgb(18,53,33)`, 10px |
| band ground | `g-900` | `rgb(14,42,28)` |

Full bleed, square corners, and the pulse cell runs to the container's outer
edge (the prototype's `padding-right:0`, mirrored).

**One mismatch I found cell by cell and fixed:** the change figure was pushed to
the far end of each cell with `ms-auto`. The prototype sets it immediately after
the value with an 11px gap. A gap between a print and its change reads as two
separate readings. Removed.

The "as of" and source sit in a darker strip inside the band, not on the cream.

**Flat cells carry no glyph** — `0.00%` alone, as the prototype has it. An arrow
that points nowhere is still an arrow. Screen readers still get بلا تغير.

---

## 2. Big story

> Superseded in part by §2b and §2c below: the stage is full bleed and 800px
> tall, the scrim has been re-profiled twice, and the headline sizing in the
> table below was re-measured at both the new width and the new height.

### It is a real carousel now

You described a prev control, a thumbnail of the next slide, and a next control.
That only exists if there are other slides, so `big_story` now resolves three
and the layout pins three. Non-functional controls would have been the fifth
"dead code that looks alive" find of this project, so I built the thing the
controls imply rather than the controls alone.

Working: mouse and keyboard, wraps in both directions, exactly one headline and
one thumbnail in view at each step. The thumbnail is the slide the next button
goes to, so the affordance is not decoration. With one slide pinned, no controls
and no Alpine component render at all — tested.

Cost: **+1.36 KB gzipped**. The two extra photographs are inside `display:none`
subtrees, so the browser does not fetch them until the reader advances.

Heading levels: slide 1 is the `<h1>`, slides 2 and 3 are `<h2>`. The document
keeps exactly one `h1`. axe is clean on all three slides.

### The headline constraint is measured, not chosen

You asked for ~52px, never a fourth line, never reaching the photograph's
midpoint, tested against the longest demo headline. I ran all 72 published
headlines through the real element at 1440px:

| | headlines needing 4 lines | ink reaches |
|---|---|---|
| 52px / 17ch | 5 | clears midpoint by 11px |
| 52px / 18ch | 0 | **33px past the midpoint** |
| 50px / 18ch | 0 | **9px past the midpoint** |
| **48px / 18ch** | **0** | **clears by 14px** |
| 47px / 19ch | 0 | **4px past the midpoint** |

At 52px the two constraints cannot both hold. **48px/18ch is the only pair that
satisfies both**, with 14px of margin. That is the number in the file, with the
sweep written into the comment beside it. The 3-line clamp stays as a backstop
for a headline longer than any we have.

Below `lg` there is no midpoint to respect, so the headline takes the full
column and up to four lines rather than truncating on a phone.

### Everything else in the stage

- One photograph across the **whole** stage, edge to edge inside the container.
  No inset, no radius, no border.
- **No panel behind 01/02/03.** The steps sit on the same photograph under the
  lighter end of the scrim. The prototype has a solid `g-900` panel there; your
  instruction says there is none, and your instruction wins.
- Label is plain white uppercase letterspaced — not the prototype's outlined
  chip.
- Button is an outlined transparent pill with a thin white border, arrow after
  the label — not the prototype's solid white fill.
- Numerals gold at 44px, each with **its own** vertical hairline, so a two-line
  step and a four-line step still read as equals. The prototype has them mint at
  25px with no rule.
- Stage height 470px exactly.

### The one thing the prototype has that I did not copy

The prototype italicises the final phrase of the headline. Readex Pro has no
italic and Arabic has no cursive variant, so a browser would synthesise an
oblique — a slanted Arabic word, not an emphasised one. Colour carries it alone.
Same call as before, restated because it is visible in the side-by-side.

---

## 2b. Big story, second pass — the six problems

You were right, and the measurement says why. Screenshot of the rendered section
beside the prototype's is in the scratchpad; the photograph now runs the full
width in mine.

**1. Margin and radius.** The section carried `margin: 24px 0` — a measured gap
between it and the ticker. Removed. Radius was already 0. It is now full bleed
to the viewport edges like the ticker band, with the type on the page's own
1300px grid so the headline still lines up with the sections below it. Measured
at nine widths from 1024 to 2560: section x=0, width = viewport, gap to the
ticker = 0, at every one.

Full bleed uses `margin-inline: calc(50% - 50vw)` with `overflow-x: clip` on the
body — `clip` and not `hidden`, because `hidden` makes the body a scroll
container and the sticky masthead stops sticking. Verified: after scrolling
2000px the header still reports `top: 0`.

**2. The photograph as background.** It was a child of the stage grid. It is now
a section-level layer, exactly the shape you wrote: `<img absolute inset-0
object-cover>` per slide, then the scrim, then the grid on top. Pinned by a test
that walks the DOM and asserts the section's children are the image layers, then
the scrim, then the grid.

**3. No panels.** There were none left on the columns — `background-color` on
both measured `rgba(0,0,0,0)` before this pass. The same test now asserts no
column carries a `bg-` class, because a background put back there renders
perfectly and looks deliberate.

**4. The gradient — this was the real fault.** The direction was right (I
sampled the scrim alone over white: 79% dark at the right edge falling to the
left). What was wrong is that **it never reached transparent**: 55% darkening at
60% across and still 18% at the far edge. That is what made the photograph read
as a middle box between two panels. Fixed.

The new profile is not a taste, it is a constraint. The type covers most of the
picture: the headline runs from the start edge to **46%** across, and the
questions column's text sits between **74% and 93%**. Only the band between them
can be left alone, so the scrim is solid where the headline is, **fully
transparent from 56% to 67%**, dark again under the questions, then easing back
at the far edge.

**And the light scrim exposed a real bug.** With it, white type on slides 2 and
3 failed contrast badly — their photographs have a blown-out sky where the
questions column sits. Measured off the rendered pixels, worst line box of each
text run across all three slides:

| | before (light scrim) | shipped |
|---|---|---|
| headline | 1.97:1 | **7.13:1** |
| standfirst | 2.91:1 | **7.65:1** |
| byline | — | **6.41:1** |
| step label | 2.89:1 | **13.44:1** |
| step body | 2.04:1 | **7.53:1** |

Two notes on how those were measured, because my first two attempts were wrong.
I sampled element boxes rather than line boxes, which measured the empty half of
a right-aligned `<p>` and reported failures no reader could see; and I had one
selector matching two different elements, which is why changing the gradient did
not move the numbers. Both fixed before the profile was chosen. The step body
also went from `cream/75` to full cream — it sits at the far edge where the
scrim does least.

The stop values are in the comment in `app.css` with those numbers beside them.
Lighten the 46% or 71% stop and the questions column drops under 4.5:1 again.

**5. The headline.** The cap was `lg:max-w-[18ch]` — a `lg:`-only constraint, so
below 1024px it did not apply at all, and between 1024 and 1264px a fixed 48px
over 18ch ran up to **76px past the photograph's midpoint**. The cap is now
unconditional and the size tracks the viewport in that band —
`clamp(2.375rem, 3.8vw, 3rem)` — capping at 48px above 1264.

Re-tested by substituting all **72** published headlines into the real element
at five widths:

| width | size | headlines needing a 4th line | worst midpoint margin |
|---|---|---|---|
| 1024 | 38.9px | 0 | 13px clear |
| 1180 | 44.8px | 0 | 21px clear |
| 1280 | 48px | 0 | 34px clear |
| 1440 | 48px | 0 | 44px clear |
| 1920 | 48px | 0 | 44px clear |

**6. Height.** 470px at all nine widths. Nothing set a viewport height; the
stage's only height rule is the 470px minimum.

### Verified after this pass

627 tests (2043 assertions), Pint clean, axe 0 violations on four public pages
and on carousel slides 2 and 3, carousel still working by mouse and keyboard in
both directions, no horizontal overflow at 400px, sticky masthead intact.

---

## 2c. Big story, third pass — scale

> The 800px height here was overshoot and is superseded by §2d. Everything else
> in this section still stands: the crop finding, the `sizes` fix, the scrim
> going horizontal, and the LCP measurement.

You were right that 470 was the fault, and it was hiding three other things.

**The stage is 800px at desktop** (`min-h-[50rem]`), 544px below 1024 keeping the
proportions. Measured at nine widths from 1024 to 2560: 800px at every one.

| | reference | rendered |
|---|---|---|
| section height | ~800px | 800px |
| headline | ~72px, 3 lines, generous leading | **68px**, 3 lines, 1.1 |
| standfirst | ~19px, 3 lines | 19px, clamped to 3 |
| step numerals | ~44px gold | 44px, `rgb(201,160,99)` |
| step question | ~13px uppercase letterspaced | 13px, 0.12em |
| step answer | ~14.5px, 2 lines | 14.5px, clamped to 2 |
| gap between steps | ~90px | 90px, spread down the column |
| THE BIG STORY | ~13px, top-start, well above | 13px, top of the column |

The label is now hoisted out of the slide loop so the column can be
`justify-between` — label pinned top, story pinned bottom, and the space between
them is the section's height doing the work rather than a margin.

### The headline is 68px, not 72, and here is why

At 72px the constraint is no longer the `ch` cap — it is the column. The
headline column's content box is 796px at 1440, which is 17ch at 72px, so
widening the cap past 17 changes nothing. Swept against all 72 published
headlines:

| size | 13ch | 14ch | 15ch | 16ch | 17ch | 18ch | 19ch |
|---|---|---|---|---|---|---|---|
| 72px | 47 | 30 | 18 | 6 | 5 | 5 | 5 |
| 68px | 47 | 30 | 18 | 6 | 5 | **0** | 0 |
| 64px | 47 | 30 | 18 | 6 | 5 | 0 | 0 |

(headlines needing a fourth line)

**68px/18ch is the largest pair where all 72 fit in three lines.** The longest of
them ends at the column's own inner edge, so the headline never reaches the
questions beside it. Re-verified at six widths: 0 four-line headlines, all
inside the column, at 1024 / 1180 / 1280 / 1440 / 1680 / 1920.

If you want 72px, the cost is five of the demo headlines running to four lines —
say the word and I will take the clamp off instead.

### 1. The crop — the height did solve it

At 800px the stage is 1.800:1 and `home.jpg` is 1.776:1. Measured on the
rendered element: **100% of the width and 99% of the height survive the crop.**
No focal points needed.

It did surface a stale hint, though. `home.blade.php` was passing
`sizes="(max-width: 1024px) 100vw, 62vw"` — left over from when the stage was
container-width. Full bleed makes it 100vw at every size, so the browser had
been picking a candidate for 62% of the screen and stretching it across all of
it. Fixed, and the preload's `imagesizes` follows it.

### 2. The scrim had to stop being diagonal

288deg is 18 degrees off vertical. That was survivable at 470px; at 800px the
tilt dominates. Measured over white, the clear band sat at 50–60% across the top
of the box and 60–75% across the bottom — so it slid out from under the
questions column and the dark end slid out from under the headline. Measured
contrast at the new height with the old angle: headline **1.12:1**, step body
**2.46:1**.

It is horizontal now, so a percentage means the same thing at every height. The
headline runs to 61% across and the questions text sits between 75% and 93%,
which leaves 14% of the width for two ramps and a clear window — that is why
there are twelve stops. Four made both knees show as hard vertical lines down
the photograph, which I saw in the screenshot and fixed.

Worst line box of each text run, all three slides, off rendered pixels:

| | 1440px | 400px |
|---|---|---|
| headline | 6.95:1 | 9.10:1 |
| standfirst | 7.80:1 | 5.52:1 |
| byline | 6.28:1 | 6.18:1 |
| step label | 6.94:1 | 10.10:1 |
| step body | 5.10:1 | 8.60:1 |

**Below 1024 the scrim is a flat wash, not a gradient.** One column means the
type runs the full width and passes straight through the clear window: measured
at 400px before this existed, the headline was at **1.39:1** and the step text
at **1.02:1**. A layout with no empty side has no side to leave alone.

Two of my own measurements were wrong before they were right, both the same
class of error as the capture bug you caught earlier: an element screenshot
scrolled the section under the sticky masthead and captured the cream header as
if it were the photograph, and a clip taller than the viewport made puppeteer
stitch the header into the middle of the frame. Both produced confident failure
numbers. The fix in each case was to look at the captured image.

### 3. LCP

**2039 ms and 2020 ms on two runs** — Lighthouse mobile, slow-4G, compression at
the edge. Performance 98, CLS 0.007, TBT 0. Phases: TTFB 5% · Load Delay 25% ·
Load Time 70% · Render Delay 0%. Well inside your 4s line.

I am not claiming that as a 1.6s improvement on the recorded 3685 ms, because I
cannot re-run the old build on this harness and the harnesses may not match.
What I can say is what this one measured and that it was honest: **my first run
today said 2.9s and was wrong** — the stylesheet is emitted with an absolute URL
built from `APP_URL`, so it was fetched straight from :8000 and arrived
uncompressed, 61.6 KiB of it. Lighthouse's `uses-text-compression` audit scored
0 and said so. The proxy now rewrites those URLs, and the audit passes with no
savings left on both runs above.

**One thing full bleed cost, which you should decide on.** The hero's largest
conversion is 1800w. At 1440 CSS px on a 2× screen the box wants 2880 device px,
so the hero is served at 0.63× — soft on a retina desktop. It was fine before,
when the stage was 884px wide. Mobile is unaffected (412 × 2.625 = 1082 px, well
inside 1800w). Fixing it means adding a ~2400 conversion, which re-runs the
image seeder over all 28 photographs and buys LCP back the other way. I have not
done it.

### Colours, sampled

- Ticker band: was `g-900`, now **`g-950`** — near-black green. mint-2 measures
  11.0:1 on it, coral 7.13:1, cream 16.41:1. The "as of" strip now separates
  with a hairline rather than a darker ground, there being nothing darker.
- Step numerals: `#C9A063`, measured on the rendered element.
- Emphasis: mint, final phrase only.
- Standfirst: `cream/75` — light warm grey, not white.
- Button: transparent, 1px white border at 70%.

### Verified

627 tests (2043 assertions), Pint clean, axe 0 violations on four public pages
and on slides 2 and 3, carousel working by mouse and keyboard, no horizontal
overflow at 400px, sticky masthead intact.

---

## 2d. Big story, fourth pass — 620px, and tested at the fold

**620px at desktop.** Tested the way you asked rather than against the number —
one screenshot at 1440x900, no scrolling:

| | |
|---|---|
| masthead bottom | 122 |
| ticker bottom | 205 |
| big story | 205 → 825 (620px) — **fits** |
| headline bottom | 615 — visible without scrolling |
| next section top | 825 — visible |

75px of the next section shows beneath the fold. One correction to your
description: in our layout order the section under the big story is **الأبرز**
(the three leads), not the tiles — tiles are two sections down. If you want the
tiles to be what peeks through, that is a composer reorder, not a height.

### 1. The headline is 56px

Swept against all 72 published headlines at the 620px stage, against three
constraints at once — no fourth line, no spill past the column's bottom edge,
never leaving the column into the questions beside it:

| size | 15ch | 16ch | 17ch | 18ch | 19ch | 20ch |
|---|---|---|---|---|---|---|
| 60px | 18 | 6 | 5 | **0** | 0 | 0 |
| 56px | 18 | 6 | 5 | **0** | 0 | 0 |
| 52px | 18 | 6 | 5 | **0** | 0 | 0 |

(headlines needing a fourth line; bottom-spill and column-overflow were 0 in
every cell)

18ch is the narrowest cap that works, at every size in the range, so **56px/18ch
holds** — your number, with the cap that satisfies the other two constraints.
Re-verified at six widths: 0 four-line headlines, all inside the column, at 1024
through 1920.

### 2. The three steps are a group again

`justify-center` with **60px** between them, and the bottom padding that was
reserving space for the controls is gone — the controls are absolutely
positioned, so they were never in the flow and the reserve was only pushing the
group off-centre.

### 3. No ellipsis

Two separate sources, and the first one hid the second. The template ran the
answer through `Str::limit($text, 120)` — a literal `...` written by PHP, which
no CSS check can see — and then `line-clamp-2` added its own on top.

`Str::limit` is gone. For the clamp, I measured the real capacity instead of
guessing: **259px of text at 14.5px holds about 90 characters on two lines**.
The seeded answers were 97–123. You offered either room or shorter text; the
answers live in `ArabicContent` and are also the four-question columns on the
article page, where the blocks carry the elaboration, so shorter is the honest
fix rather than a workaround. All twelve rewritten to 73–85 characters, no
figures invented, then each one rendered into the actual element and checked:
**all twelve fit two lines, none clips.**

One of them clipped at 83 characters on the first pass because its last word
would not fit — character count is a proxy, not a guarantee, which is why the
test asserts a budget of 88 and the rendered check is what settled it.

### The scrim moved again, because the headline did

At 56px the headline only reaches 51% across instead of 61%, which frees the
clear window to start earlier — and the steps, now grouped in the middle,
landed on a brighter part of slide 2 and dropped to **4.00:1** before I
re-profiled. After:

| | 1440px | 400px |
|---|---|---|
| headline | 7.65:1 | 9.1:1 |
| standfirst | 7.67:1 | 5.5:1 |
| byline | 6.4:1 | 6.2:1 |
| step label | 12.41:1 | 10.19:1 |
| step body | 10.22:1 | 8.22:1 |

### The carousel controls did not move

They are at `bottom-7 end-7` and have been since the first build — in RTL that
is the bottom-**left**, the inline-end corner, under the questions column and
over the photograph. Asked, and you confirmed that is where they belong. Nothing
changed; flagging it only so the record shows it was checked rather than assumed.

### Verified

629 tests (2070 assertions), Pint clean, axe 0 violations on four public pages
and on slides 2 and 3, carousel working by mouse and keyboard, no horizontal
overflow at 400px, `migrate:fresh --seed` reproducing cleanly.

Two tests added, both for failure modes that are silent: one asserts the answer
reaches the page whole and that no `...` appears anywhere in the rendered hero,
the other holds every seeded answer inside the measured two-line budget.

---

## 3. Tiles

Six across. Bordered white card, 1px `--line`, **0px radius, no shadow**
(measured). Photograph flush to the card's top edge with no padding around it.
Title 19px. Description two lines, clamped, `--ink-3`. Filled circular arrow on
`g-900` at the bottom start edge, pinned to the card's floor with `mt-auto` so
six different blurb lengths share one baseline.

**One divergence:** the prototype crops tile images at `1/.8`. Our six named
crops are in `App\Support\AspectRatio` and a template names a role, never a
number, so these use `card` (16:10) — slightly wider than the prototype's. A
seventh crop is a design-system change and I did not make one unasked.

---

## 4. Masthead

78px. MASAR at 31px with 0.08em letterspacing (measured: 31px / 2.48px), مسار
beside it at 26px. Vertical hairline, then the tagline at 9px / 1.5 line-height
/ 0.1em in a 96px column. Nav uppercase at 11.5px / 0.07em, full bar height so
the active 2px rule lands on the masthead's own edge. Hamburger — three 1.5px
bars, 22px wide — after a vertical hairline at the end side.

**The Latin wordmark now leads the lockup** and is the larger of the two. It was
the other way round, on my reasoning that an Arabic-first product leads with the
Arabic mark. Your spec describes MASAR as the large mark, and the prototype has
it first, so I changed it. Say the word and it goes back.

**The burger is no longer small-screen only.** It was `lg:hidden`; the drawer is
the whole navigation and the bar shows eight of it, which is as true at 1440px
as at 390px.

**Two settings where there was one.** The topbar and the masthead were both
printing `identity.tagline` — the same string twice, eighty pixels apart. The
prototype has two different lines. Added `identity.descriptor` ("أعمال · اقتصاد
· أسواق · فرص") for the topbar, with a field on the settings screen.

**Your tagline renders on two lines, not three.** "من المعلومة إلى الفرصة" is
short; the 96px column is the prototype's. That is content, not markup — a
longer tagline in الهوية gives you the third line.

---

## Two things I did not fix, and one number that changed

**The نمو figure.** Flagged above. Yours to revert.

**Podcast and reports render one row each.** بودكاست has a template for three
rows and resolved one; تقارير has a template for four and resolved one. Both are
filtered to a `content_type` and both run near the end of the layout, by which
point the earlier unfiltered sections have consumed almost every interview and
report in the pool. I confirmed this is **not** caused by the carousel: with
`big_story` set back to 1 the counts are identical. There are 8 published
interviews and 8 reports; the article appetite of the whole layout is 70 against
72 published. Fixing it means either reserving by type in the composer or moving
the typed sections earlier — a composer design decision, so I left it.

**The demo picks moved.** `big_story` now takes picks 0–2, so leads moved to
3–4, video to 5–7 and issue to 8–13, and the pick query fetches 16 instead of
12. Noted because a future "why is the lead story different" starts here.

---

## Verification

- `php artisan test` — **626 passed, 2031 assertions**. Seven new, all asserting
  mechanisms with silent failure modes: series parsing, no-chart-without-points,
  the flat-series centre line, the settings round-trip for `series`, the
  backdrop staying on the lead slide, and no controls on a one-slide layout.
- `pint --test` — passed.
- axe (wcag2a/aa, 21a/aa, 22aa) — **0 violations** on `/ar`, `/ar/saudi`,
  `/ar/markets`, `/ar/search`, and 0 on the carousel at slides 2 and 3.
- 400px: no horizontal overflow, nothing overhanging the viewport. The ticker
  drops its sparklines below `sm` — two cells to a row leave no space for a
  chart beside a six-figure print, and the figure is the part that has to
  survive.
- `migrate:fresh --seed` — 97 articles, 72 published, 28 images, 8 clips, 7
  market settings rows, 6 sources, 22 intelligence items, 1 illustrative
  backdrop. 18 homepage sections, **none empty**.

---

## Files

**Changed**

- `app/Support/MarketFigures.php` — `series` on every figure row
- `app/Filament/Pages/ManageSettings.php` — series field, `identity.descriptor`
- `app/Enums/HomepageSectionType.php` — `BigStory` 1 → 3
- `config/masar.php` — `identity.descriptor` default
- `database/seeders/MarketFiguresSeeder.php` — per-row shapes, نمو direction
- `database/seeders/DemoContentSeeder.php` — three pinned slides, picks realigned
- `resources/views/components/data/sparkline.blade.php` — `stroke` prop, flat centring
- `resources/views/components/data/as-of.blade.php` — merges attributes
- `resources/views/components/layout/ticker.blade.php` — rebuilt
- `resources/views/components/layout/masthead.blade.php` — rebuilt
- `resources/views/components/layout/mega-menu.blade.php` — one link treatment for all items
- `resources/views/components/layout/mobile-nav.blade.php` — burger at every width
- `resources/views/components/layout/topbar.blade.php` — descriptor
- `resources/views/components/home/big-story.blade.php` — rebuilt as a carousel
- `resources/views/components/home/tiles.blade.php` — rebuilt as bordered cards
- `resources/css/app.css` — `--color-mint-2`, `--color-coral`, `--color-g-850`;
  `.hero-scrim` re-profiled against measured contrast; `.full-bleed`;
  `overflow-x: clip` on the body

**Tests**

- `tests/Feature/Public/MarketFiguresTest.php` — four added
- `tests/Feature/Public/IllustrativeMediaTest.php` — five added
- `database/factories/Support/ArabicContent.php` — the twelve four-question
  answers shortened to the measured two-line budget
- `tests/Feature/SiteControl/SiteControlPagesTest.php` — one added

**To run**

```
php artisan migrate:fresh --seed
npm run build
```

---

## Media out of the repository

The push was rejected on two 4K stock clips. All twelve videos were introduced
in the one unpushed commit, so the fix needed no rewrite of published history
and no force-push.

| file | source | before | after | outcome |
|---|---|---|---|---|
| 9339478-uhd_3840_2160_24fps.mp4 | 3840×2160 | 167.4 MB | — | dropped: 23.0 MB after transcode, still over budget |
| 10407687-hd_3840_2160_30fps.mp4 | 3840×2160 | 101.2 MB | — | dropped: 24.2 MB after transcode, still over budget |
| 9789926-uhd_3840_2160_30fps (1).mp4 | 3840×2160 | 40.9 MB | — | dropped: byte-identical duplicate |
| 9789926-uhd_3840_2160_30fps.mp4 | 3840×2160 | 40.9 MB | 2.54 MB | kept → `9789926-web-1920x1080.mp4` |
| 19745264-uhd_3840_2160_60fps (1).mp4 | 3840×2160 | 25.2 MB | — | dropped: 20.4 MB after transcode, still over budget |
| 19102789-uhd_3840_2160_24fps.mp4 | 3840×2160 | 20.2 MB | 1.87 MB | kept → `19102789-web-1920x1080.mp4` |
| 12709488_1080_1920_30fps (1).mp4 | 1080×1920 | 19.5 MB | 5.93 MB | kept → `12709488-web-1080x1920.mp4` |
| 16398507_2880_1440_24fps.mp4 | 2880×1440 | 18.5 MB | 2.17 MB | kept → `16398507-web-1920x960.mp4` |
| 16457007_3840_2160_50fps.mp4 | 3840×2160 | 13.1 MB | 2.26 MB | kept → `16457007-web-1920x1080.mp4` |
| 16391149_3840_2160_50fps.mp4 | 3840×2160 | 9.2 MB | 1.69 MB | kept → `16391149-web-1920x1080.mp4` |
| 13047044_3840_2160_25fps.mp4 | 3840×2160 | 5.2 MB | 0.48 MB | kept → `13047044-web-1920x1080.mp4` |
| 12175940_1440_1920_30fps.mp4 | 1440×1920 | 3.4 MB | 1.42 MB | kept → `12175940-web-1440x1920.mp4` |

**464.6 MB → 18.4 MB, 96% smaller.** Eight kept, four dropped. The three that
stayed over budget are the same three `VideoLibrarySeeder` was already refusing
to ship, so nothing the site renders was lost — the seeder still reports 8 clips
within budget and 6 articles given a player.

The transcode uses the seeder's own recipe — 1920 on the long edge, H.264 high,
CRF 25, 30fps, faststart, no audio — so the file in the repository and the file
the seeder ships are the same rendition rather than two different ones.

Renamed to `<id>-web-<width>x<height>.mp4`. The old names claimed 3840×2160 for
files that are now 1920×1080, and a name-shaped `.gitignore` rule would have
silently excluded the renditions along with the masters.

**History, not just the working tree.** `git reset --soft HEAD~1` then a rebuilt
commit, so the blobs were never in the tree that gets pushed; `git reflog expire`
+ `git gc --prune=now` dropped them locally. The largest blob anywhere in the
repository is now 6.33 MB. The push is a fast-forward — origin/main is still an
ancestor — so it needs no force.

**The size rule is a test**, because `.gitignore` cannot express one.
`tests/Feature/RepositoryWeightTest.php` fails on any tracked file over
`masar.media.max_video_kb`, on any tracked name carrying a master's shape, and
on any shipped clip over the delivery budget. It caught the staged masters
before the commit, which is what it is for.

Masters kept at `storage/app/video-masters/` (untracked — Laravel already
ignores `storage/app/*`), so nothing is lost if you want to re-source the three.

---

## Redesign pass 1 — palette and the intelligence map

### Palette

The tokens are now the reference's, and every ratio below is measured off the
new values rather than carried over:

| token | was | now | measured |
|---|---|---|---|
| `--g-950` | `#071A10` | **`#001D17`** | cream 15.79 · mint 7.63 · gold 7.32 · coral 6.98 |
| `--g-900` | `#0E2A1C` | **`#002E24`** | cream 13.25 · mint 6.40 · gold 6.14 · coral 5.86 |
| `--g-850` | `#123521` | **`#0A3D30`** | 1.22 against the band |
| `--g-700` | `#1A4531` | **`#0F4A3B`** | 9.09 on cream |
| `--g-600` | `#1E5E3F` | **`#14614C`** | 6.60 on cream, 7.38 on white |
| `--cream` | `#F6F4EF` | **`#F5F2E9`** | warm ivory |
| `--line` | `#E2DED4` | **`#E4DFD1`** | 1.19 against cream |
| `--ink` | `#14181A` | **`#0D1C17`** | 15.69 on cream — very dark green, not black |
| `--ink-3` | `#61696F` | **`#5E6A63`** | 5.05 on cream |
| `--gold-ink` | `#8F6828` | **`#8A6526`** | 4.73 on cream, 5.29 on white |

`--gold`, `--mint`, `--mint-2` and `--coral` are unchanged — they were already
the reference's muted gold and soft mint.

Everything downstream was re-measured, not assumed. The hero scrim is built on
`--g-950`, so its eleven stops were re-checked against all three slides: worst
line box now **headline 12.57:1, standfirst 7.43:1, byline 6.30:1, step label
11.81:1, step body 10.01:1** at 1440, all passing at 400px too. Ten components
quoted specific ratios in their `contrast-safe` notes; those figures were stale
the moment the tokens moved, so they were updated to the new measurements.

CLAUDE.md's brand token block is rewritten to match.

### The world map

Land only, and decorative. **Natural Earth 1:110m via `world-atlas` (public
domain)**, converted here to a single SVG path and dissolved to a fill with no
contrasting stroke — adjacent countries merge into one silhouette, so it draws
**coastlines and no political border at all**. That is what makes it shippable:
the objection I raised earlier was to drawing borders, not to drawing a map.

No labels, no per-country colour, nothing encoded in it. The coverage list in
front of it is what carries meaning. It does not mirror in RTL — a mirrored
world is a wrong world, not a translated one.

`public/svg/world-outline.svg`, 18.9 KB raw / **8.3 KB gzipped**, lazy, below the
fold inside a `content-visibility` subtree, so it costs nothing on first paint.
Three artefacts had to be fixed before it was usable, each visible only by
rendering it: white slivers where I had filtered points mid-ring, hairline seams
where simplification made shared country edges diverge, and two full-width lines
where rings crossing the antimeridian jumped the map.

### Still outstanding from the brief

- **Typography hierarchy pass** — sizes are as measured against the prototype
  over the last four rounds, not yet re-derived against the reference.
- **Intelligence subtitle and the four named tabs** — tabs render from the
  editor's own `content_type` grouping and only appear when there is more than
  one group; the demo currently has one.
- **Opportunity feature** — exists and is dark with the image, but not yet
  matched to the reference's diagonal composition.
- The thirteen `/images/*.jpg` filenames in the brief do not exist; the Media
  Library fallback is intact and nothing breaks without them.

---

## Redesign pass 2 — typography, intelligence, opportunity feature

Measured against the attached reference this time, not the prototype. Taking the
reference's content width (~1707px in the shot) onto our 1300px container gives
a scale of about 0.76, and applying it showed the same story everywhere: the
wordmark, the bars and the card titles were all too small, the story numerals
slightly too large.

### Typography

| | was | now | reference implies |
|---|---|---|---|
| MASAR wordmark | 31px | **56px** | ~59px |
| مسار | 26px | **36px** | — |
| masthead height | 78px | **100px** | ~100px |
| topbar height | 42px | **48px** | ~48px |
| main nav | 11.5px | **12px → 12.5px at 2xl** | ~17px (see below) |
| ticker band | 54px | **60px** | ~61px |
| ticker label | 8.5px | **11px** | ~14px |
| ticker value | 14px | **15px** | ~15px |
| hero headline | 56px | **60px** | ~63px |
| hero kicker | 13px | **12px**, wider tracking | ~12px |
| standfirst | 19px | **17.5px** | ~17.5px |
| story numeral | 44px semibold | **38px regular** | ~38px, light |
| story label | 13px | **11.5px** | ~11.4px |
| card title | 19px | **22px** | ~23px |
| card description | 11.2px | **12.5px** | — |

The headline was re-swept at the new size: all 72 published headlines still fit
three lines and stay inside the column at 1024 / 1180 / 1280 / 1440 / 1680 /
1920. The hero scrim was re-measured after both the palette change and the
headline change — worst line box now headline 11.92:1, standfirst 7.43:1, step
body 9.85:1 at 1440, all passing at 400px.

### MASAR Intelligence

Four fixed tabs, always present: **نبض السوق · قطاعات صاعدة · رادار الفرص ·
أرقام رئيسية**, plus the subtitle «رؤى حقيقية. فرص حقيقية.»

They no longer come from `content_type` grouping, which is why they used to
vanish — the demo has one group. They are the section's editorial shape, and
each is backed by data the CMS already holds: the regional pulse figures, the
articles grouped by category, the articles carrying an `opportunity`, and the
`market.data` figures. Each renders the existing labelled empty state when its
data has not been entered, so a tab never disappears and never invents a number
to fill itself. No schema or CMS change.

### Opportunity feature

Rebuilt as a feature rather than a card: dark `g-950` ground, the photograph
entering from the end side behind a **slanted seam**, small mint category label,
large headline, paragraph, outlined CTA, `min-h-[22rem]`.

The seam is a `clip-path` with separate RTL and LTR polygons — a clip-path does
not mirror on its own, and the dark half stays the wider one at the bottom in
both, which is the reference's composition rather than a blind mirror of it. A
gradient over the seam keeps the photograph bleeding into the green instead of
being pasted onto it.

### Two overflows the reference pass introduced, both caught by measuring

The bigger nav broke the layout twice, and neither was visible in a 1440
screenshot:

- **At 1024** the eight Arabic labels came to 745px and could not sit beside a
  56px wordmark; the burger was pushed **242px off the start edge** and the page
  scrolled to 1266. The desktop nav now starts at `xl` and the drawer carries
  navigation below that.
- **At exactly 1280** it still did not fit (1307). The nav now steps: 12px with
  an 18px gap from `xl`, 12.5px with a 22px gap from `2xl`.

My first overflow check missed both, because it tested `right > clientWidth` —
and **RTL overflows to the left**. Elements were sitting at negative x and
widening the scroll area while every right edge looked fine. The check now tests
both edges.

### Validation

632 tests (2089 assertions), Pint clean, axe 0 violations on `/ar`, `/ar/saudi`,
`/ar/markets`, `/ar/search` and on carousel slides 2 and 3. No horizontal
overflow at 400 / 768 / 1024 / 1280 / 1360 / 1440 / 1536 / 1600 / 1920 / 2560.
Whole hero still inside a 900px viewport with the next section showing.

### Remaining differences from the reference, named

1. **Nav type is 12px against the reference's ~17px.** Eight Arabic labels —
   «الشركات الناشئة وريادة الأعمال» among them — are far longer than eight
   English words. At 17px they do not fit beside the wordmark at any width below
   1900. This is the one place the reference's proportions cannot be reproduced
   with this menu.
2. **The "as of" strip under the ticker** has no counterpart in the reference.
   It is required: a figure without a visible date and source does not publish.
3. **The headline sets three lines, the reference four.** Arabic is more compact
   than English at the same measure.
4. **The hero crop differs** — the reference's tower sits centre-right, ours
   centre-left. That is the source photograph, not the layout.
5. **Story numerals are Readex figures, not the reference's light serif.** No new
   font family, per your instruction.
6. **The thirteen `/images/*.jpg` names still do not exist.** Media Library
   fallback intact; nothing breaks without them.

---

## Redesign pass 3 — final visual polish

### 1. Hero crop — and what the LTR render proved

I checked the library first. One other frame carries the Kingdom Centre
(`glenov-brankovic`, «برج المملكة في الرياض ليلاً») but it is a night,
street-level shot beside a purple-lit façade — not a skyline, and nothing like
the reference's dusk. The other Riyadh frames are KAFD. **`home.jpg` is the only
image in the library with the reference's composition**, so this was a crop
question, as you said.

Measuring it: the tower sits at **37.8% from the image's left edge**, and the
stage is 2.32:1 against a 1.775:1 source, so `cover` crops the *vertical* only —
there is no horizontal slack to shift at all. Moving the tower right would mean
zooming in, and the sunset the composition is built on is to the tower's right,
so the zoom would crop the sunset out.

But the real answer came from rendering the page in LTR, which I did to exercise
the mirrored CSS. **In LTR the tower lands centre-right, exactly as in the
reference.** It reads as "too far left" in RTL only because the layout mirrors
and the photograph does not — and it must not: a mirrored photograph of a real
building is a fabrication.

Relative to the text, which is what the composition is actually about:

| | tower position, measured from the headline's own edge |
|---|---|
| reference (LTR, headline left) | ~58% |
| ours (RTL, headline right) | **62%** |

Four points apart, inside my measurement error off a screenshot. So the
horizontal is right and I have not degraded the hero's resolution to chase it.

What I *did* fix is the vertical, where there was real slack and a real
difference: the crop was centred and throwing the sky away. A new `focal` prop
on `responsive-image` holds it at **50% 34%**, which keeps the amber sunset the
reference is built on. Visible in the screenshots as considerably more sky.

### 2. Headline — four lines

The reference's four-line rhythm comes from a narrower measure, not a bigger
size. Swept against all 72 headlines at the 620px stage:

| cap | >4 lines | exactly 4 lines | spills bottom |
|---|---|---|---|
| 12ch | 13 | 52 | 13 |
| 13ch | 1 | 46 | 1 |
| **14ch** | **0** | **30** | **0** |
| 15ch | 0 | 18 | 0 |

**68px over 14ch**: 30 of 72 now set four lines and none sets more, where 18ch
put nearly all of them on three. 13ch reads better still — 46 of 72 at four —
but one headline runs to five and spills past the column's bottom edge, so 14ch
is the widest rhythm that is safe for every headline we have.

### 3. Story numerals

Readex ships only 500 and 600, so the `font-normal` I had set was silently
resolving to 500 — there was no light weight to get. They now use the body face,
IBM Plex Sans Arabic, which **does** ship 400: `font-sans`, 42px, weight 400,
`tracking-[0.02em]`, `text-gold/85`. Still one of the two existing families; no
new font.

### 4. Navigation

Not 17px — that measured 750px of labels and broke 1280. Instead the tracking
went from 0.07em to **0.03em**, and the width that buys went into size:
**12.5px at xl, 13px from 1400px**. Up from 12px, with the same breakpoints and
every item kept.

### 5. As-of strip

Folded into the band: same ground, no rule above it, 10px, `cream/55`, sitting
as the band's caption rather than a bar under it.

**It caught me out once.** I first set it to `cream/45`, which measured
**4.09:1** — axe flagged it on `/ar` as a serious contrast violation. `cream/55`
is 5.48:1. That is the floor, and the comment in the file says so.

### 6. Proportions now

| | reference implies | rendered |
|---|---|---|
| topbar | ~48px | 48px |
| masthead | ~100px | 100px |
| wordmark | ~59px | 56px |
| ticker band | ~61px | 60px |
| hero | ~563px | 620px |
| hero headline | ~63px | 68px |
| story numeral | ~38px light | 42px, weight 400 |
| card title | ~23px | 22px |

### Validation

632 tests (2089 assertions), Pint clean, axe **0 violations** on `/ar`,
`/ar/saudi`, `/ar/markets`, `/ar/search` and on carousel slides 2 and 3. No
horizontal overflow at 400 / 768 / 1024 / 1280 / 1360 / 1400 / 1440 / 1536 /
1600 / 1920 / 2560 — checked from **both** edges, since RTL overflows left.

LTR verified by flipping the document direction, because `en` is disabled in the
locale config and `/en` 404s: the scrim flips to 90deg, the diagonal seam flips
to its LTR polygon, and nothing overflows either edge.

### Remaining differences, actually compared against the reference

1. **Nav is 13px against the reference's ~17px.** Eight Arabic category labels
   are far longer than eight English words; 17px does not fit beside the
   wordmark below ~1900px. Shorter labels would close it; type alone cannot.
2. **The "as of" line has no counterpart in the reference.** It stays — a figure
   without a visible date and source does not publish — but it is now quiet.
3. **42 of 72 headlines still set three lines, not four.** Getting more of them
   to four costs a headline that overflows the stage.
4. **The hero is 620px against the reference's ~563px.** 620 is what keeps the
   whole section inside a 900px viewport with the next section showing, which
   was your earlier constraint and I have kept it.
5. **Story numerals are IBM Plex figures, not the reference's light serif.** No
   new font family, per your instruction.
6. **The thirteen `/images/*.jpg` names still do not exist.** Media Library
   fallback intact.

---

## Redesign pass 4 — the stack, not the section

You were right that measuring the section in isolation was the wrong unit.

**Before**

| | px |
|---|---|
| topbar | 49 |
| masthead | 101 |
| ticker band | 60 |
| as-of row | 24 |
| big story | 620 |
| **stack** | **854** |

854 in a 900px viewport leaves 46px of the next section — not "beginning to
show", and on a real 900px window, once browser chrome is taken off, the button
is below the fold.

**After**

| | px |
|---|---|
| topbar | 49 |
| masthead | **81** |
| ticker band (as-of now inside it) | **60** |
| big story | **520** |
| **stack** | **710** |

**190px of the next section shows.** Byline bottom 592, button bottom 666, step
03 bottom 618 — all comfortably inside, with 234px of headroom against 900.

### Masthead

Wordmark 56 → **44px**, مسار 36 → **28px**, bar 101 → **81px**, and the nav
links dropped to the same 80px so they sit on the wordmark's optical line rather
than below it.

### As-of

No longer a row. It is now the **first cell of the ticker band**, on the same
60px row as MARKET PULSE, start-aligned at 10px in `cream/55`. Costs zero
vertical height at desktop.

Not a tooltip: hiding it behind a hover would put a publish-gate requirement
behind an interaction, and out of reach of anyone not using a pointer. Below lg
the band wraps and the caption takes its own line — capped at 32px there rather
than a full 60px cell, which was 36px of a phone spent on a caption.

### The headline survived the shorter stage

Re-swept all 72 against 520px: **68px/14ch still holds** — 0 headlines exceed
four lines, 30 of 72 set four, **0 spill past the column's bottom edge**, none
leaves the column. 13ch would give 46 at four lines but spills 47 of them at
this height. No change needed to the size or the cap.

### Validation

632 tests (2089 assertions), Pint clean, axe 0 violations on four pages. No
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920, checked from both edges. Hero
contrast re-measured after the stage shrank — headline 11.06:1, standfirst
7.64:1, byline 6.00:1, step body 9.94:1 at 1440; all passing at 400px.

One note on method: the machine ran out of memory partway through this pass
(71 MB free) and Chrome screenshots began timing out while `evaluate` calls kept
working. The numbers above are from `evaluate`; the one screenshot was taken at
1x as JPEG after freeing memory.

---

## Redesign pass 5 — scale down

Every size you listed is now set exactly. Measured at 1440x900, DPR 1, root
16px:

| | target | rendered |
|---|---|---|
| MASAR wordmark | 38px | **38px** |
| مسار | optical match | 24px |
| nav label | 12.5px | **12.5px** |
| ticker value | 15px | **15px** |
| ticker change | 12px | **12px** |
| ticker label | 9px | **9px** |
| ticker band | 54px | **54px** |
| masthead | 78px | **79px** (78 + its 1px rule) |
| headline | 48px | **48px** |
| standfirst | 15px | **15px** |
| step numeral | 42px | **42px** |
| step question | 12px | **12px** |
| step answer | 13.5px | **13.5px** |
| big story | 520px | **520px** |

Stack at 1440x900: 49 + 79 + 54 + 18 + 520 = **720**, with **180px of the next
section showing**. Button bottom 676, step 03 bottom 624.

### Three of the structural findings did not reproduce

Measured before changing anything:

- **Container is 1300px.** The cap is applied.
- **The grid is `884px 416px`** — exactly 1.36fr : 0.64fr. The steps column is
  **32%** of 1300, not a near-even split. Worth noting the spec and the
  impression disagree: 0.64fr *is* about a third. A true quarter is 1.5fr/0.5fr,
  which is a one-line change if you want it — I have not made it, because you
  asked for 1.36/0.64 and that is what is rendering.
- **The gap below the section is 0px.** الأبرز starts immediately.

Several of your "now" figures were also already at or below target before this
pass — ticker value 15px, step numerals 42px, big story 520px, step question
11.5px against a 12px target. The likeliest explanation for the discrepancy is
browser zoom or display scaling on your side; the numbers above are CSS pixels
at DPR 1.

### A regression this pass introduced, and caught

Moving the as-of caption into the band as a seventh cell took 168px of 1300 and
left the five instrument cells at 192px, where **every instrument value
clipped** — the sparklines ran into the numbers. The band holds the instruments
or the caption at this width, not both, and the instruments are what a reader
came for. It is a caption row again, but 18px rather than the 24px it was, at
9px. Cells are back to 226px with nothing clipped.

Worse: while rewiring it, the caption stopped rendering **entirely** for a
while, and the suite stayed green. The existing test rendered the `as-of`
component in isolation, which only ever proved the component works — so a figure
sat on the page with no visible date beside it, which is the one thing the gate
forbids.

There is now a test that renders the **ticker** and asserts the value, the date
and the source all appear in it. Same lesson as §7: assert on the surface that
shows the figure, not on the partial.

### Validation

633 tests (2092 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920 from both edges.

---

## Redesign pass 6 — the hero crop

### The focal shift never applied, and I reported it as done

`object-position` measured **50% 50%** — the default. The `focal="50% 34%"` I
wrote in pass 3 never reached the component: the edit anchored on a string with
24 spaces of indentation and the call site has 20, so the replacement silently
matched nothing. Pass 3 claimed the shift as delivered. It was not.

Nothing was scaling beyond `cover` — `transform: none`, `object-fit: cover`.
The tight slice was entirely the stage's aspect against the source's.

### The numbers behind the slice

| | before | after |
|---|---|---|
| stage | 1440x520, **2.769:1** | 1440x600, **2.400:1** |
| source | 1800x1014, 1.775:1 | same |
| drawn at cover | 1440x811 | 1440x811 |
| cropped vertically | 291px | **211px** |
| visible rows | 146–666 of 811 | **84–684 of 811** |
| share of the frame | **64%** | **74%** |
| object-position | 50% 50% | **50% 40%** |

Chosen by rendering the source beside five candidate crops and looking, not by
picking a number: 560/40% still clipped the base, 640/40% added mostly empty sky
at the top. 600 at 40% is where the tower reads whole from its top to its base,
with the sunset behind it and the lit streets along the bottom edge.

Stack at 1440x900 is now **800** — 49 + 79 + 54 + 18 + 600 — with **100px of the
next section showing**. Button bottom 756, step 03 bottom 664.

### The empty band

Measured every gap between consecutive sections. **The gap after the big story
is 0px**; the next section's first painted element sits at 799 against a section
bottom of 800.

The only band after the hero is **52px**, and it is between الأبرز and
استكشف مسار — the section rhythm the prototype runs and CLAUDE.md records
("52px above a rule, 20px below"). It is not adjacent to the big story.

The reference does put the category tiles directly under the hero. Ours has
الأبرز between them, which is the layout an editor pinned in the composer. That
is a `sort_order` change, not a CSS one, and editorial precedence says a stored
editorial choice is not mine to overwrite — say the word and it is one line.

### Validation

633 tests (2092 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920 from both edges. Hero contrast
re-measured on the new crop — headline 13.40:1, standfirst 7.81:1, byline
6.00:1, step body 9.94:1. Headline still 30 of 72 at four lines, none over, none
leaving the column.

---

## Redesign pass 7 — section order, and a keyboard path to it

### The composer is draggable, and that was the gap

Section order **is** exposed to an editor: `homepage-composer.blade.php` puts
`draggable="true"` on each row with `dragstart`/`dragover`/`drop` handlers
calling `$wire.reorderSections`, and the handler validates that every id belongs
to the layout before writing. Tested both ways. No developer needed.

But **HTML5 drag-and-drop cannot be operated from a keyboard at all.** For the
life of this page an editor without a pointer could hide a section and delete a
section but not move one — while §5 makes keyboard navigation a floor and TASK
04's premise is that the owner changes the site without a developer, which has
to include an owner using a keyboard.

Added `moveSection($id, ±1)` with up/down buttons beside the existing eye and
trash, each with an Arabic `aria-label` naming the section, disabled at the ends.
It rewrites the whole run rather than swapping two values, because seeded rows
can share a `sort_order` and a swap between equals moves nothing. Two tests: one
that a keyboard move reorders, one that it refuses to run past either end, on a
bad direction, or on another layout's section.

### الأبرز moved below the tiles

Changed in `DemoContentSeeder` so a fresh install gets it, and applied to the
current database. Order is now big_story → tiles → leads → …

The 52px rule rhythm above the tiles then read as a gap between the photograph
and the strip, so the first section after the big story renders its rule
`tight`. Measured: the gap from the hero to the tiles is **0px**, and the 52px
rhythm resumes from السعودية onward where it belongs.

### CLAUDE.md §7

> After an edit that should change rendered output, verify the output changed.
> An edit tool that anchors on exact text fails by matching nothing, and a
> silent no-match produces the same console output as a successful edit.

### Validation

635 tests (2095 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920 from both edges. Stack at
1440x900 is 800 with the tiles beginning at 800.

### Push readiness, re-verified

- `aa3aa16`, fast-forward onto `origin/main` — **no force needed**
- **349 blobs, 101.2 MB, largest 6.33 MB**; zero over GitHub's 100 MB limit
- **8 renditions** in `public/images`, 0.48–5.93 MB, all 1080p H.264
- **0 master-shaped names tracked**; largest blob anywhere in the repo is a 6.33
  MB photograph
- `RepositoryWeightTest` green

---

## Redesign pass 8 — the sparklines

### The flat one was the dangerous one

Twelve points would have broken USD/SAR. The old code normalised every series
to the full box, so a pegged rate whose whole range is a rounding error at its
own level would have been drawn as **the most volatile line on the band** — a
false picture of the steadiest instrument there, which is the same class of
problem as an invented figure.

So the geometry moved into `App\Support\Sparkline`, where it is testable, with
amplitude damping: a series whose range is 4% or more of its own level gets the
whole box; anything flatter gets proportionally less. Measured on the rendered
paths:

| | points | curve segments | vertical swing |
|---|---|---|---|
| تاسي | 12 | 11 | 16.60 / 18px (92%) |
| نمو | 12 | 11 | 16.60 / 18px (92%) |
| برنت | 12 | 11 | 16.60 / 18px (92%) |
| الذهب | 12 | 11 | 16.60 / 18px (92%) |
| **دولار/ريال** | 12 | 11 | **2.66 / 18px (15%)** |

Visible movement, clearly minimal — which is what a peg is. Not the dead
straight line it was, which reads as missing data.

### The curve

Catmull-Rom through the points, converted to cubic Béziers, `stroke-linejoin`
and `stroke-linecap` round. It **passes through every point an editor typed**
rather than approximating them — a smoothing that moved the points would be
drawing a different series from the one entered, and there is a test asserting
the path starts and ends exactly on the first and last coordinates.

### Size

46x18 at 1.4px stroke, up from 32x15 at 1.3. That costs 14px the cell did not
have and **every instrument value clipped**. The 14px came out of the cell's
padding (24px → 20px) and gap (11px → 8px) rather than out of the figure. Cells
are 226px, nothing clipped.

### Seeds

Twelve points each, shaped to the story rather than the sign — تاسي rising with
a visible pullback, نمو shallower and choppier, برنت falling through a failed
recovery, الذهب rising with small oscillations, دولار/ريال tiny noise around
its line.

**نمو carries the shape you described for S&P 500**: we swapped that instrument
for Nomu early on for Saudi focus and you approved it, so the second riser is
the one that got "rising, shallower, choppier".

Twelve comma-separated numbers is one line of input, so the settings field
needs no change — only its helper text, which still said six to eight.

### One thing that broke and why it was right to

`MarketFiguresTest` asserted `substr_count($html, '<polyline')`, and the
component now emits `<path>`. The mechanism it guards — a row with no points
gets no chart — is intact; the assertion just named the tag. Updated to assert
the chart, not the element.

### Validation

641 tests (2106 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920. `/ar/markets` still renders
its filled index chart from the same component.

---

## Redesign pass 9 — sparkline range, and the seam

### Your diagnosis was right; your fix would have broken the other end

I measured before changing anything. `getBBox()` on the rendered paths said the
four movers were already drawing **16.6 of 18px — 92%** of their box, from
y=0.7 to y=17.3. So the damping was not clamping them, and the lines were using
the height.

The reason it still read thin is that the *seeded* series were arbitrary shape
numbers (34–63), whose range is 27–60% of their own level. Those sail past any
threshold. **The threshold was never tested by the data it was meant to
govern** — and that hid a real bug: an editor typing actual prices would have
been flattened, because a real trading day is about 1% of level, not 30%.

Re-seeded at each instrument's own level, here is what the three candidate
curves do:

| | span | range/level | old, linear 4% | **linear 1.5%** | **shipped: √, 1.5%** |
|---|---|---|---|---|---|
| تاسي | 170 | 1.515% | 38% | 100% | **100%** |
| نمو | 240 | 0.914% | 23% | 61% | **78%** |
| برنت | 1.22 | 1.479% | 37% | 99% | **99%** |
| الذهب | 30 | 1.290% | 32% | 86% | **93%** |
| دولار/ريال | 0.0006 | 0.016% | 0% | **1%** | **10%** |

You asked me to say if 1.5% was wrong in the other direction. **It is.** At a
linear 1.5% the peg lands at 1% of the box — a dead straight line, which is the
exact failure you named last round: it looks like missing data.

A linear map cannot hold both ends. An index moving 1.5% and a peg moving 0.016%
are two orders of magnitude apart; any straight line through them either
flattens the index or shouts the peg. So the response is now **compressive** —
the square root of range-over-level against a 1.5% reference. Movers 78–100%,
peg 10%.

The box also went from 18px to 22px tall. Rendered extents now: تاسي 94%,
برنت 93%, الذهب 87%, نمو 73%, peg 10% — the movers travel 20.7px where they
travelled 16.6px, and nothing in the cell clips.

### The seam

Measured 0px — nav bottom 200, section top 200, genuinely flush. But a solid
g-950 strip meeting the photograph's lighter sky still draws a hard horizontal
edge, which is what you were seeing. `.hero-seam` fades the top 64px of the
frame out of the band so the picture emerges from it instead of starting at a
line. It only ever darkens, so it cannot cost contrast.

### Two tests failed and both were right to

- The big story's markup test pinned **5** section children; the seam makes
  **6**. Updated, and it now asserts the seam layer by name.
- The peg test's fixture was a 0.6% range, calibrated to the old linear map.
  Under the compressive curve a 0.6% range is a real move, correctly. Replaced
  with the peg's actual 0.016% band, and the assertion is now the relationship
  the damping exists to hold: a trading day clears 18 of 22px, the peg stays
  under 4px and under a quarter of the day's travel, but above 0.5px so it is
  never a dead line.

### Validation

641 tests (2109 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920. Ticker cells 226px, no value
clipped.

---

## Redesign pass 10 — the scrim was the whole thing

### You were right about the green

The gradient's stops computed to `color(srgb 0 0.113725 0.0901961)` — `#001D17`,
`--color-g-950`, a dark **green**. I audited every layer for anything else that
could cast: section background `rgba(0,0,0,0)`, no filter, no `mix-blend-mode`,
all three image layers at opacity 1 with no filter. **Nothing else. The gradient
was the entire tint.** A coloured scrim does not darken a photograph, it
recolours it.

It is neutral black now, and confined: solid at the headline edge, gone by 45%,
nothing after. The second dark zone under the questions column is removed.

`.hero-seam` was green too, for the same reason. Also black now.

### The crop was never wrong

The Kingdom Centre sits at **~57% across, complete, skyline on both sides** —
which is the reference's ~60%. `object-position` computes `50% 40%`, and with a
1800x1014 source in a 1440x600 box `cover` crops the **vertical only**, so there
is no horizontal crop to get wrong.

It read as clipped because the green wash had flattened the towers into one teal
mass. Fixing the scrim fixed the crop complaint, because they were the same
complaint.

### The softness is real, and 1800 is not enough

`naturalWidth: 1440` was a red herring — with `w` descriptors and `sizes`, the
browser density-corrects it: 1800w ÷ 1440px sizes = 1.25 density, 1800/1.25 =
1440. I checked the bytes on the wire instead: the served
`home-backdrop-large.webp` **is 1800x1014 of real pixels**.

But the stage is full bleed. At 1440 CSS px and DPR 2 the box needs **2880
device pixels** and is getting 1800 — **0.625x**. That is the softness, and
`home.jpg` is 1800 wide, so 1800 is the most that can ever be served.

**Taking you up on the offer: please supply a larger master.** 2880 wide covers
desktop retina; 3600 would also cover a 1.5x ultrawide. I have not touched the
conversion ladder — there is nothing to gain from it until the master is bigger,
and widening it now would only upscale.

### Solving the steps locally, as instructed

With the scrim confined, the questions column sits on the bare photograph —
fine on a night skyline, **1.10:1** on a slide with a blown-out sky.
`.steps-scrim` darkens that column only and fades to nothing before it reaches
the picture. Measured across all three slides:

| | before | after |
|---|---|---|
| step body | 1.10:1 | **8.10:1** |
| step label | 1.59:1 | **9.07:1** |
| headline | — | 5.51:1 |
| standfirst | — | 4.60:1 |
| byline | — | 4.79:1 |

All pass at 400px too, worst 5.18:1.

### Three bugs in my own instrument

The contrast sampler hides the type to photograph the ground beneath it, and it
was lying three times over:

1. It hid the **whole questions column**, which hid the new local scrim with it
   — so it measured a ground that no reader ever sees and reported a failure
   that had already been fixed.
2. Once that was narrowed to the text, it stopped hiding the **carousel
   controls**, and sampled a white button border as "sky": 1.06:1 against a real
   ground of `(1,6,4)`.
3. Narrowing the hide selector left the **unhide** selector behind, so the next
   button stayed invisible, every click missed, and all three slides reported as
   slide 1 — with numbers that looked plausible.

Hide and unhide are now keyed off a `data-ground-hidden` marker rather than two
selectors that can drift apart.

### Validation

641 tests (2109 assertions), Pint clean, axe 0 violations on four pages, no
overflow at 400 / 768 / 1024 / 1280 / 1440 / 1920.

### Not yet done from your list

The band height and cell layout, the inline "as of", the masthead, the 830px
stage with the headline centred, and the step sizing. You said to do the scrim
first and look — it changed the picture enough that those measurements are worth
re-taking against what is on screen now.
