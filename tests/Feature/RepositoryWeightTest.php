<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * The repository is not object storage.
 *
 * Two 4K masters — 167 MB and 101 MB — reached a commit and GitHub rejected the
 * push at its 100 MB hard limit. Deleting them from the working tree would not
 * have helped: the blobs were already in the pack, and the push would have
 * failed on exactly the same objects.
 *
 * `.gitignore` cannot express a size rule, so this is where the size rule
 * lives. It fails on the file, by name, before it can reach a commit.
 */
function trackedFiles(): array
{
    $process = new Process(['git', 'ls-files', '-z'], base_path());
    $process->run();

    if (! $process->isSuccessful()) {
        return [];
    }

    return array_filter(explode("\0", $process->getOutput()));
}

it('keeps every tracked file under the media budget', function (): void {
    $files = trackedFiles();

    if ($files === []) {
        $this->markTestSkipped('not a git checkout');
    }

    // The same ceiling the video pipeline already enforces, so one number
    // governs what ships and what may be committed.
    $ceiling = (int) config('masar.media.max_video_kb') * 1024;

    $oversized = [];

    foreach ($files as $file) {
        $path = base_path($file);

        if (! is_file($path)) {
            continue;
        }

        $size = filesize($path);

        if ($size > $ceiling) {
            $oversized[] = sprintf('%s (%.1f MB)', $file, $size / 1048576);
        }
    }

    expect($oversized)->toBe([], sprintf(
        "tracked files over %.0f MB:\n  %s\n\nMedia belongs in object storage. ".
        'Transcode it, or move it to storage/app/video-masters and untrack it.',
        $ceiling / 1048576,
        implode("\n  ", $oversized),
    ));
});

it('tracks no video master', function (): void {
    $files = trackedFiles();

    if ($files === []) {
        $this->markTestSkipped('not a git checkout');
    }

    // A rendition is named `<id>-web-<width>x<height>.mp4`. Anything tracked
    // that still carries a master's shape in its name is a master, whatever
    // its current size says.
    $masters = array_values(array_filter(
        $files,
        fn (string $f): bool => (bool) preg_match('/(\.(mov|mkv|avi|mxf)$|-uhd_|_3840_2160_|_2880_1440_|_2160p|-master\.mp4$)/i', $f),
    ));

    expect($masters)->toBe([], 'video masters are tracked: '.implode(', ', $masters));
});

it('ships no video over the delivery budget', function (): void {
    $ceiling = (int) config('masar.media.max_video_kb') * 1024;
    $videos = glob(public_path('images').'/*.mp4') ?: [];

    expect($videos)->not->toBeEmpty();

    foreach ($videos as $video) {
        expect(filesize($video))->toBeLessThanOrEqual(
            $ceiling,
            basename($video).' is over the delivery budget',
        );
    }
});
