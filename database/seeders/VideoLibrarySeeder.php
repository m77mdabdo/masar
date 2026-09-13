<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Article;
use App\Models\HomepageLayout;
use App\Queries\ComposeHomepage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Self-hosted video: one web rendition and one real poster image per clip.
 *
 * The sources are 4K stock masters, up to 167 MB for 58 seconds. Shipping those
 * is not a decision anybody would make on purpose, so each is transcoded once to
 * a 1080p H.264 rendition and then judged against `masar.media.max_video_kb`.
 * A clip that still does not fit is reported and left out rather than quietly
 * halved in quality or quietly shipped.
 *
 * The poster is a JPEG pulled from the rendition at seed time and registered as
 * an image with the normal conversions — so `<video preload="none">` costs one
 * poster-sized request, not a frame decoded in the browser.
 */
class VideoLibrarySeeder extends Seeder
{
    /** Seconds into the clip to grab the poster from. Frame zero is often black. */
    private const POSTER_AT = 1.0;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('VideoLibrarySeeder skipped: local environment only.');

            return;
        }

        if (! $this->hasFfmpeg()) {
            $this->command?->warn('VideoLibrarySeeder skipped: ffmpeg not on PATH.');

            return;
        }

        $sources = $this->sources();

        if ($sources->isEmpty()) {
            $this->command?->warn('VideoLibrarySeeder skipped: no .mp4 found in public/images.');

            return;
        }

        [$shippable, $oversized] = $this->prepare($sources);

        foreach ($oversized as $name => $megabytes) {
            $this->command?->warn(sprintf(
                'Video over budget, not shipped: %s (%.1f MB transcoded, budget %.1f MB).',
                $name,
                $megabytes,
                config('masar.media.max_video_kb') / 1024,
            ));
        }

        if ($shippable->isEmpty()) {
            $this->command?->warn('VideoLibrarySeeder: every clip is over budget, nothing attached.');

            return;
        }

        $attached = $this->attach($shippable);

        $this->command?->info(sprintf(
            'Video library: %d clips within budget, %d over, %d articles given a player.',
            $shippable->count(),
            count($oversized),
            $attached,
        ));
    }

    private function hasFfmpeg(): bool
    {
        $probe = new Process(['ffmpeg', '-version']);
        $probe->run();

        return $probe->isSuccessful();
    }

    /**
     * Source clips, de-duplicated by content.
     *
     * Two of the files are byte-identical copies of one another; hashing is
     * cheaper than a reader noticing the same footage twice on one page.
     *
     * @return Collection<string, string> basename => path
     */
    private function sources(): Collection
    {
        $seen = [];

        return collect(File::glob(public_path('images').'/*.mp4'))
            ->sort()
            ->filter(function (string $path) use (&$seen): bool {
                $hash = md5_file($path);

                if (isset($seen[$hash])) {
                    return false;
                }

                $seen[$hash] = true;

                return true;
            })
            ->mapWithKeys(fn (string $path): array => [basename($path) => $path]);
    }

    /**
     * Transcode each source once and split the results by the size budget.
     *
     * @param  Collection<string, string>  $sources
     * @return array{Collection<string, array{video: string, poster: string}>, array<string, float>}
     */
    private function prepare(Collection $sources): array
    {
        $dir = storage_path('app/seed-video');
        File::ensureDirectoryExists($dir.'/posters');

        $shippable = collect();
        $oversized = [];
        $budget = (int) config('masar.media.max_video_kb') * 1024;

        foreach ($sources as $name => $path) {
            $stem = str(pathinfo($name, PATHINFO_FILENAME))->slug()->value();
            $video = $dir.'/'.$stem.'.mp4';
            $poster = $dir.'/posters/'.$stem.'.jpg';

            if (! File::isFile($video)) {
                // First run on a fresh checkout transcodes every clip, which
                // takes minutes, not seconds. Said out loud so it reads as work
                // rather than as a hang.
                $this->command?->warn("  transcoding {$name} — first run only, this is slow.");
                $this->transcode($path, $video);
            }

            if (! File::isFile($video)) {
                continue;
            }

            if (filesize($video) > $budget) {
                $oversized[$name] = filesize($video) / 1048576;

                continue;
            }

            if (! File::isFile($poster)) {
                $this->grabPoster($video, $poster);
            }

            $shippable[$stem] = ['video' => $video, 'poster' => $poster, 'source' => $name];
        }

        return [$shippable, $oversized];
    }

    /**
     * 1080p on the long edge, H.264 high profile, faststart so playback can
     * begin before the file has finished arriving. Audio is dropped: these are
     * silent stock clips, and an empty audio track is bytes for nothing.
     */
    private function transcode(string $source, string $target): void
    {
        $process = new Process([
            'ffmpeg', '-y', '-loglevel', 'error',
            '-i', $source,
            '-vf', "scale='min(1920,iw)':'min(1920,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
            '-r', '30',
            '-c:v', 'libx264', '-preset', 'slow', '-crf', '25',
            '-pix_fmt', 'yuv420p', '-profile:v', 'high',
            '-movflags', '+faststart',
            '-an',
            $target,
        ], timeout: 1800);

        $process->run();
    }

    private function grabPoster(string $video, string $target): void
    {
        $process = new Process([
            'ffmpeg', '-y', '-loglevel', 'error',
            '-ss', (string) self::POSTER_AT,
            '-i', $video,
            '-frames:v', '1',
            '-q:v', '3',
            $target,
        ], timeout: 120);

        $process->run();
    }

    /**
     * Give the articles in the homepage's video section a clip, a poster and a
     * video block — the block is what the /video listing and the article page
     * both look for.
     *
     * @param  Collection<string, array{video: string, poster: string, source: string}>  $clips
     */
    private function attach(Collection $clips): int
    {
        $articles = $this->videoSectionArticles();

        if ($articles->isEmpty()) {
            return 0;
        }

        $keys = $clips->keys()->all();
        $count = 0;

        foreach ($articles->values() as $index => $article) {
            $clip = $clips[$keys[$index % count($keys)]];

            $article->clearMediaCollection('video');
            $article->clearMediaCollection('video_poster');

            $article->addMedia($clip['video'])
                ->preservingOriginal()
                ->withCustomProperties(['source_file' => $clip['source']])
                ->toMediaCollection('video');

            if (File::isFile($clip['poster'])) {
                $poster = $article->addMedia($clip['poster'])
                    ->preservingOriginal()
                    ->withCustomProperties([
                        'alt' => $article->hero_alt ?? '',
                        'source_file' => $clip['source'],
                    ])
                    ->toMediaCollection('video_poster');

                // Belt and braces: a poster with no conversions would fall back
                // to the full-size frame, which is the download we are avoiding.
                $poster->refresh();
            }

            $article->blocks()->where('type', 'video')->delete();
            $article->blocks()->create([
                'type' => 'video',
                'data' => ['self_hosted' => true, 'title' => $article->title],
                'sort_order' => (int) $article->blocks()->max('sort_order') + 1,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, Article>
     */
    private function videoSectionArticles(): Collection
    {
        $layout = HomepageLayout::query()->where('is_active', true)->first();

        if ($layout === null) {
            return collect();
        }

        $section = app(ComposeHomepage::class)($layout, config('masar.locales.default', 'ar'))
            ->firstWhere('type', 'video');

        return collect($section['items'] ?? [])
            ->filter(fn ($item): bool => $item instanceof Article)
            ->values();
    }
}
