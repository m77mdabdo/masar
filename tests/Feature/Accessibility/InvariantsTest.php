<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Support\Facades\File;

/**
 * Accessibility properties that a template edit can quietly remove.
 *
 * axe found zero violations across nineteen pages at two viewports, which is
 * worth knowing and is not worth much on its own: the things that broke here
 * were a removed focus outline and a target two pixels under the minimum, and
 * an automated checker reports neither. These are the ones a test can hold.
 */
it('never removes the focus outline from a control', function (): void {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        if (preg_match('/(focus|focus-visible):outline-none|outline:\s*none/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    /*
     * Three inputs carried `focus:outline-none` with only a 1px border colour
     * change behind it — the newsletter band, the newsletter page and the site
     * search box, which is to say every text input a reader ever touches. A
     * keyboard user had no indication of where they were. WCAG 2.4.7.
     *
     * The site-wide floor lives in app.css as `:focus-visible { outline: 2px }`;
     * this test is what stops a template opting out of it again.
     */
    expect($offenders)->toBe([], 'Focus outline removed in: '.implode(', ', $offenders));
});

it('gives a silent clip a text alternative and says that it is silent', function (): void {
    $article = Article::factory()->create();

    // A real clip, because the collection refuses anything that is not video —
    // which is itself the upload guard working.
    $media = $article->addMedia(public_path('images/13047044-web-1920x1080.mp4'))
        ->preservingOriginal()
        ->withCustomProperties([
            'alt' => 'لقطات لميناء تجاري ورافعات تفرغ حاويات.',
            'has_audio' => false,
        ])
        ->toMediaCollection('video');

    $html = (string) view('components.ui.video-player', [
        'media' => $media,
        'poster' => null,
        'title' => 'العنوان',
        'description' => null,
        'ratio' => 'hero',
        'autoplay' => false,
        'attributes' => new Illuminate\View\ComponentAttributeBag,
    ])->render();

    expect($html)->toContain('aria-describedby')
        ->and($html)->toContain('لقطات لميناء تجاري')
        // WCAG 1.2.1: video-only prerecorded media owes a text alternative, and
        // a reader who cannot hear needs to know silence is the clip, not a
        // fault at their end.
        ->and($html)->toContain('مقطع بلا صوت');
});

it('claims nothing about sound when nobody measured it', function (): void {
    $article = Article::factory()->create();

    $media = $article->addMedia(public_path('images/13047044-web-1920x1080.mp4'))
        ->preservingOriginal()
        ->withCustomProperties(['alt' => 'لقطات لميناء تجاري.'])
        ->toMediaCollection('video');

    $html = (string) view('components.ui.video-player', [
        'media' => $media,
        'poster' => null,
        'title' => 'العنوان',
        'description' => null,
        'ratio' => 'hero',
        'autoplay' => false,
        'attributes' => new Illuminate\View\ComponentAttributeBag,
    ])->render();

    // has_audio is absent, so the player describes the footage and says nothing
    // either way about sound — a page may not describe what nobody established.
    expect($html)->toContain('لقطات لميناء تجاري')
        ->and($html)->not->toContain('مقطع بلا صوت');
});
