<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Exceptions\IllustrativeMediaRejected;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Models\Media;
use Database\Factories\Support\ArabicContent;
use Illuminate\Http\UploadedFile;
use Illuminate\View\ComponentAttributeBag;

/**
 * A generated image may carry mood behind a headline. It may never illustrate a
 * story, a company or a place — a reader has no way to tell it from the real
 * photographs around it, and that is exactly what would make it read as
 * reportage.
 *
 * The restriction is enforced in code, not by convention, so these assert the
 * mechanism: not "no page currently shows it" but "the code will not let it".
 */
function illustrativeMedia(): Media
{
    $layout = HomepageLayout::factory()->create(['is_active' => true]);

    // A real JPEG: the collection checks the detected MIME type, not the
    // extension, which is the rule that keeps a renamed file out.
    $media = $layout->addMedia(UploadedFile::fake()->image('backdrop.jpg', 120, 68))
        ->withCustomProperties(['alt' => 'أفق مدينة عند الغروب.', 'credit' => 'صورة تعبيرية'])
        ->toMediaCollection('backdrop');

    $media->forceFill(['is_illustrative' => true])->save();

    return $media->refresh();
}

it('refuses an illustrative image as an article hero', function (): void {
    $media = illustrativeMedia();

    $article = Article::factory()->create();

    expect(fn () => $article->forceFill(['hero_media_id' => $media->getKey()])->save())
        ->toThrow(IllustrativeMediaRejected::class);
});

it('refuses an illustrative image attached to an article at all', function (): void {
    $article = Article::factory()->create();

    // Not only the hero: the inline collection, or any future one, would put it
    // inside the body where a reader takes it for a photograph of the subject.
    $media = $article->addMedia(UploadedFile::fake()->image('inline.jpg', 120, 68))
        ->toMediaCollection('inline');

    expect(fn () => $media->forceFill(['is_illustrative' => true])->save())
        ->toThrow(IllustrativeMediaRejected::class);
});

it('still allows a real photograph as an article hero', function (): void {
    $article = Article::factory()->withHeroImage()->create();

    // The guard must not be a blanket ban: everything else on the site is a
    // real photograph and has to keep working.
    expect($article->refresh()->hero_media_id)->not->toBeNull()
        ->and(Media::query()->whereKey($article->hero_media_id)->value('is_illustrative'))->toBeFalsy();
});

it('lets the layout hold an illustrative backdrop', function (): void {
    $media = illustrativeMedia();

    expect($media->is_illustrative)->toBeTrue()
        ->and($media->model_type)->toBe('homepage_layout')
        ->and($media->getCustomProperty('credit'))->toBe('صورة تعبيرية');
});

it('scopes reportage away from illustrative imagery', function (): void {
    illustrativeMedia();
    Article::factory()->withHeroImage()->create();

    expect(Media::query()->illustrative()->count())->toBe(1)
        ->and(Media::query()->reportage()->where('is_illustrative', true)->count())->toBe(0);
});

it('names no city in the backdrop alt text', function (): void {
    // The moment it is captioned as a real place it stops being mood and
    // becomes a claim about a location that this arrangement of towers does not
    // support.
    $alt = (string) illustrativeMedia()->getCustomProperty('alt');

    foreach (['الرياض', 'جدة', 'الدمام', 'Riyadh', 'Jeddah'] as $place) {
        expect($alt)->not->toContain($place);
    }

    expect($alt)->toContain('مدينة');
});

it('never renders the backdrop on an article page', function (): void {
    $backdrop = illustrativeMedia();

    $article = Article::factory()->withHeroImage()->withSources()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => Category::query()->firstOrCreate(
            ['slug' => 'saudi'],
            ['is_active' => true, 'sort_order' => 1, 'color' => '#0E2A1C'],
        )->id,
    ]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('backdrop.jpg');
});

it('puts the backdrop behind the lead slide only', function (): void {
    $backdrop = illustrativeMedia();

    $slides = collect(range(1, 3))->map(fn (): Article => Article::factory()->withHeroImage()->create());

    $html = view('components.home.big-story', [
        'section' => ['title' => 'القصة الكبرى', 'items' => $slides],
        'backdrop' => $backdrop,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    $sources = collect()->wrap(preg_match_all('/(?:src|srcset)="([^"]+)"/', $html, $m) ? $m[1] : [])
        ->implode(' ');

    // The lead slide shows the generated backdrop and not its own article's
    // photograph; every other slide shows its own. A reader turning the
    // carousel must never land on a story illustrated by something invented.
    expect($sources)->toContain('backdrop')
        ->and($sources)->not->toContain($slides[0]->heroMedia->file_name)
        ->and($sources)->toContain(pathinfo($slides[1]->heroMedia->file_name, PATHINFO_FILENAME))
        ->and($sources)->toContain(pathinfo($slides[2]->heroMedia->file_name, PATHINFO_FILENAME));
});

it('renders no carousel controls when the layout pins a single story', function (): void {
    $html = view('components.home.big-story', [
        'section' => ['title' => 'القصة الكبرى', 'items' => collect([Article::factory()->withHeroImage()->create()])],
        'backdrop' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    // Controls that cannot move anything are dead code that looks alive.
    expect($html)->not->toContain('الشريحة التالية')
        ->and($html)->not->toContain('x-data');
});

it('keeps the photograph as the section background and the columns transparent', function (): void {
    $html = view('components.home.big-story', [
        'section' => ['title' => 'القصة الكبرى', 'items' => collect(range(1, 3))->map(fn (): Article => Article::factory()->withHeroImage()->create())],
        'backdrop' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    // The shape, asserted rather than eyeballed: the picture and the scrim are
    // section-level layers, and nothing over them carries a ground of its own.
    // Put a background back on either column and the photograph is boxed
    // between two solid panels again — which is a thing that renders perfectly
    // and looks, at a glance, deliberate.
    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $section = $xpath->query('//section')->item(0);

    expect($section->getAttribute('class'))->toContain('full-bleed');

    $children = [];
    foreach ($section->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $children[] = $node->getAttribute('class');
        }
    }

    // Three image layers, then the scrim, then the seam fade, then the grid —
    // all siblings of the section, none of them wrapping the type.
    expect($children)->toHaveCount(6)
        ->and($children[0])->toContain('absolute inset-0')
        ->and($children[3])->toContain('hero-scrim')
        ->and($children[4])->toContain('hero-seam')
        ->and($children[5])->toContain('grid');

    foreach ($xpath->query('//section/div[contains(@class,"grid")]/div') as $column) {
        expect($column->getAttribute('class'))->not->toMatch('/\bbg-/');
    }

    // No radius and no margin on the section or the stage: it runs corner to
    // corner and sits flush against the ticker above it. (The button and the
    // slide thumbnail are legitimately round; the section is not.)
    foreach ([$section, $xpath->query('//section/div[contains(@class,"grid")]')->item(0)] as $el) {
        expect($el->getAttribute('class'))
            ->not->toMatch('/\brounded/')
            ->not->toMatch('/\b-?m[tby]-/');
    }
});

it('shows a complete answer under each big-story step', function (): void {
    $article = Article::factory()->withHeroImage()->create();

    $html = view('components.home.big-story', [
        'section' => ['title' => 'القصة الكبرى', 'items' => collect([$article])],
        'backdrop' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    // The answer arrives whole. It used to go through Str::limit(120), which
    // put a literal ellipsis in the largest thing on the page — and PHP's cut
    // is invisible to any CSS check, so the clamp looked like the culprit.
    expect($html)->toContain($article->what_happened)
        ->and($html)->not->toContain('...');
});

it('keeps every seeded answer inside the two-line budget', function (): void {
    // 259px of text at 14.5px holds about 90 characters on two lines, measured
    // off the rendered element at 1440. Past that the clamp truncates and the
    // hero ships a visible ellipsis. 88 leaves room for an unlucky word wrap:
    // an 83-character string still clipped because its last word would not fit.
    $answers = collect([
        ArabicContent::WHAT_HAPPENED,
        ArabicContent::WHY_IT_MATTERS,
        ArabicContent::OPPORTUNITY,
        ArabicContent::BUSINESS_IMPACT,
    ])->flatten();

    expect($answers)->not->toBeEmpty();

    foreach ($answers as $answer) {
        expect(mb_strlen($answer))->toBeLessThanOrEqual(88, "too long for two lines: {$answer}");
    }
});
