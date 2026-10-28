<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\ComponentAttributeBag;

/**
 * Task 2.10: the big story drifts and advances on its own. The motion itself
 * runs in a browser and is measured there (CLAUDE.md §2). These pin the
 * contract the browser relies on, which is where the failures are silent: a
 * drift outside the reduced-motion guard, a live region that reads every
 * auto-advance aloud, a CSS duration drifting away from the JS dwell, or the
 * carousel's code creeping back in front of the LCP image would all render
 * and look fine.
 */
function bigStory(int $count = 3): string
{
    $slides = collect(range(1, $count))->map(fn (): Article => Article::factory()->withHeroImage()->create());

    return view('components.home.big-story', [
        'section' => ['title' => 'القصة الكبرى', 'items' => $slides],
        'backdrop' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();
}

function bigStoryJs(): string
{
    return file_get_contents(resource_path('js/big-story.js'));
}

it('holds the section still until its own chunk arrives after the load event', function (): void {
    $html = bigStory();

    // x-ignore: before the chunk, nothing binds, so the server frame stands.
    expect($html)->toContain('x-data="bigStory(3)" data-carousel x-ignore x-bind="root"')
        ->toContain("addEventListener('load', load, { once: true })")
        // Not on load alone: after the hero image decodes and a frame paints.
        ->toContain("document.querySelector('[data-carousel] img')?.decode()")
        ->toContain('requestAnimationFrame(() => requestAnimationFrame(() => import(')
        // @js() escapes the slashes.
        ->toContain(str_replace('/', '\\/', Vite::asset('resources/js/big-story.js')));

    // Not one byte of it in the bundle that loads before the LCP image.
    expect(file_get_contents(resource_path('js/app.js')))->not->toContain('bigStory')
        ->not->toContain('import(');
});

it('binds hover and keyboard focus as holds', function (): void {
    expect(bigStoryJs())->toContain("'@mouseenter': () => c.hold('hover', true)")
        ->toContain("'@mouseleave': () => c.hold('hover', false)")
        ->toContain("'@focusin': () => c.hold('focus', true)")
        ->toContain("'@focusout': (e) => c.leave(e)");
});

it('renders the lead slide at the drift start scale and keeps the others out of the tree', function (): void {
    preg_match_all('/<div class="hero-photo[^"]*"\s+style="([^"]*)"\s+x-bind="photo\((\d)\)"/', bigStory(), $photos, PREG_SET_ORDER);

    expect($photos)->toHaveCount(3)
        // Armed server-side, so the drift starts without a jump from 1.0.
        ->and($photos[0][0])->toContain('is-armed')
        ->and($photos[0][1])->toContain('--focal:')->not->toContain('display:none')
        // Not rendered, so their lazy images are not fetched up front.
        ->and($photos[1][1])->toContain('display:none')
        ->and($photos[2][1])->toContain('display:none')
        ->and($photos[1][0])->not->toContain('is-armed')
        ->and(array_column($photos, 2))->toBe(['0', '1', '2']);
});

it('does not move a single slide', function (): void {
    $html = bigStory(1);

    expect($html)->not->toContain('data-carousel')
        ->not->toContain('is-armed')
        // No loader: a single slide never fetches the carousel.
        ->not->toContain('const load = () => import(');
});

it('gives the reader a pause control with a state-true Arabic label and a status line', function (): void {
    $js = bigStoryJs();

    // The same circle as its neighbours by construction: it copies the next
    // button's class string rather than restating it.
    expect(bigStory())->toContain('aria-label="الشريحة التالية" data-next');
    expect($js)->toContain('button.className = next.className;')
        ->toContain("':aria-label': () => (c.playing || c.starting ? 'إيقاف العرض التلقائي' : 'تشغيل العرض التلقائي')")
        ->toContain("'x-show': () => !c.reduced")
        ->toContain("status.setAttribute('role', 'status');");
});

it('announces navigation only when the reader moved the slide', function (): void {
    // Off in the markup, and bound to `live`, which is off while it plays.
    expect(bigStory())->toContain('aria-live="off" x-bind="announcer"');
    expect(bigStoryJs())->toContain("':aria-live': () => this.live")
        ->toMatch("/get live\\(\\) \\{\\s*return this\\.playing \\? 'off' : 'polite';/");
});

it('moves only under no-preference, only by transform, and never below 1.0', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/@media \(prefers-reduced-motion: no-preference\) \{\s*\[data-carousel\](.*?)\n    \}\n/s', $css, $guard);
    expect($guard)->not->toBeEmpty();

    // Every rule that names the carousel's motion sits inside that guard.
    $outside = str_replace($guard[0], '', $css);
    expect($outside)->not->toContain('is-drifting')
        ->not->toContain('is-armed')
        ->not->toMatch('/\[data-carousel\] \.hero-photo \{\s*transition/');

    preg_match('/@keyframes hero-drift \{(.*?)\n    \}/s', $css, $frames);
    preg_match_all('/scale\(([\d.]+)\)/', $frames[1], $scales);
    expect(array_map('floatval', $scales[1]))->toBe([1.06, 1.0])
        ->and($frames[1])->not->toMatch('/width|height|background-position|translate/');
});

it('runs the drift for exactly one dwell plus one fade', function (): void {
    // The outgoing picture keeps settling under the incoming one. If the CSS
    // duration and the JS timings part company the drift either stops dead
    // before the fade or is cut off mid-move by it.
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/const DWELL = (\d+);/', bigStoryJs(), $dwell);
    preg_match('/const FADE = (\d+);/', bigStoryJs(), $fade);
    preg_match('/animation: hero-drift ([\d.]+)s linear both;/', $css, $drift);
    preg_match('/\[data-carousel\] \.hero-photo \{\s*transition: opacity (\d+)ms linear;/', $css, $transition);

    expect((int) $dwell[1])->toBeGreaterThanOrEqual(7000)->toBeLessThanOrEqual(9000)
        ->and((int) $fade[1])->toBeGreaterThanOrEqual(600)->toBeLessThanOrEqual(900)
        ->and((int) round((float) $drift[1] * 1000))->toBe((int) $dwell[1] + (int) $fade[1])
        ->and((int) $transition[1])->toBe((int) $fade[1]);
});
