<?php

declare(strict_types=1);

use App\Support\AspectRatio;
use Illuminate\View\ComponentAttributeBag;

/**
 * A self-hosted clip has to cost nothing until someone presses play, and it has
 * to never make noise on its own.
 */
function renderPlayer(array $props = []): string
{
    // A stand-in rather than a Media row: the component reads a URL and a MIME
    // type, and a real record would drag a model, a disk and a path generator
    // into what is a markup assertion.
    return (string) view('components.ui.video-player', [
        'media' => new class
        {
            public ?string $mime_type = 'video/mp4';

            public function getUrl(): string
            {
                return 'http://localhost/storage/media/1/clip.mp4';
            }
        },
        'poster' => 'http://localhost/storage/media/2/poster.jpg',
        'title' => 'تقرير مصوّر',
        'ratio' => 'hero',
        'autoplay' => false,
        'attributes' => new ComponentAttributeBag([]),
        ...$props,
    ])->render();
}

it('downloads nothing before the reader presses play', function (): void {
    expect(renderPlayer())->toContain('preload="none"');
});

it('shows a real poster image rather than decoding a frame', function (): void {
    expect(renderPlayer())->toContain('poster="http://localhost/storage/media/2/poster.jpg"');
});

it('stays inline on iOS instead of taking over the screen', function (): void {
    expect(renderPlayer())->toContain('playsinline');
});

it('reserves the box with explicit dimensions', function (): void {
    $box = AspectRatio::from('hero');

    expect(renderPlayer())
        ->toContain('width="'.$box->width.'"')
        ->toContain('height="'.$box->height.'"')
        ->toContain('ratio-hero');
});

it('never autoplays with sound', function (): void {
    $markup = renderPlayer(['autoplay' => true]);

    // If it plays on its own it is muted, always. A page that makes noise on
    // load is a bug whatever the design says.
    expect($markup)->toContain('autoplay')
        ->and($markup)->toContain('muted');
});

it('does not mark a clip autoplay unless a caller asked for it', function (): void {
    expect(renderPlayer())->not->toContain('autoplay');
});

it('offers the file when the browser cannot play it', function (): void {
    expect(renderPlayer())->toContain('متصفحك لا يدعم');
});

it('keeps the shipping budget on a clip in config, not in a template', function (): void {
    expect(config('masar.media.max_video_kb'))->toBeInt()->toBeGreaterThan(0)
        ->and(config('masar.media.accepted_video'))->toBe(['video/mp4']);
});
