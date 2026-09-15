<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Article;
use App\Models\HomepageLayout;
use App\Queries\ComposeHomepage;
use App\Support\MediaConversions;
use Database\Seeders\Support\ImageCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Turns the photography in public/images into real Media Library records.
 *
 * Two things make this a seeder rather than a one-off script. The publish gate
 * reads `articles.hero_media_id`, so a JPEG sitting on disk is invisible to it —
 * only a media row counts. And `migrate:fresh --seed` has to reproduce the site
 * we judge the design against, which means the demo articles must come back
 * carrying the same pictures.
 *
 * Runs after DemoContentSeeder: the homepage layout has to exist before we can
 * promise that no image repeats inside one of its sections.
 */
class ImageLibrarySeeder extends Seeder
{
    /**
     * Longest edge of the file we store as the "original".
     *
     * The sources run to 7108x5331. Keeping that as the stored original would
     * put ~250 MB of demo data in object storage to serve an 1800px hero, and
     * would make every conversion decode a 38-megapixel frame. 2400 is one step
     * above the widest conversion, which is the useful definition of a master.
     */
    private const MASTER_WIDTH = 2400;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('ImageLibrarySeeder skipped: local environment only.');

            return;
        }

        $sourceDir = public_path('images');

        if (! File::isDirectory($sourceDir)) {
            $this->command?->warn("ImageLibrarySeeder skipped: {$sourceDir} does not exist.");

            return;
        }

        [$catalogue, $missing] = $this->resolveCatalogue($sourceDir);

        foreach ($missing as $file) {
            $this->command?->warn("Catalogued image not on disk, skipped: {$file}");
        }

        if ($catalogue->isEmpty()) {
            $this->command?->warn('ImageLibrarySeeder skipped: no catalogued image found on disk.');

            return;
        }

        $this->registerBackdrop();

        $masters = $this->buildMasters($catalogue);
        $conversions = $this->buildConversionCache($catalogue, $masters);
        $assignments = $this->assign($catalogue);

        $this->apply($assignments, $catalogue, $masters, $conversions);

        $this->command?->info(sprintf(
            'Image library: %d images, %d article heroes.',
            $catalogue->count(),
            $assignments->count(),
        ));
    }

    /**
     * The decorative backdrop behind the big story.
     *
     * Registered separately from the catalogue, and deliberately not on an
     * article: it is generated imagery, so it may carry mood behind a headline
     * and may never illustrate a story, a company or a place. The
     * `is_illustrative` flag is what enforces that — a model guard rejects it
     * anywhere a reader would take it for reportage.
     *
     * Its alt text describes the scene and names no city. The frame reads as
     * Riyadh; the arrangement of towers is not a real one, and captioning it as
     * a real place would turn a mood image into a false claim about a location.
     */
    private function registerBackdrop(): void
    {
        $file = public_path('images/home.jpg');

        if (! File::isFile($file)) {
            $this->command?->warn('Backdrop skipped: public/images/home.jpg not found.');

            return;
        }

        $layout = HomepageLayout::query()->where('is_active', true)->first();

        if ($layout === null) {
            return;
        }

        $layout->clearMediaCollection('backdrop');

        $media = $layout->addMedia($file)
            ->preservingOriginal()
            ->usingFileName('home-backdrop.jpg')
            ->withCustomProperties([
                'alt' => 'أفق مدينة عند الغروب، تنخفض الشمس عند الأفق وتضيء الأبراج من الداخل وتسير حركة المرور على الطرق أدناه.',
                'credit' => 'صورة تعبيرية',
                'source_file' => 'home.jpg',
            ])
            ->toMediaCollection('backdrop');

        // Set after the record exists: the guard on Media rejects an
        // illustrative image owned by an article, and this one is owned by the
        // layout, so the flag is safe to write here.
        $media->forceFill(['is_illustrative' => true])->save();

        $this->command?->info('Backdrop: home.jpg registered as illustrative.');
    }

    /**
     * The catalogue filtered to what is actually on disk, keyed by filename.
     *
     * A catalogued file that has gone missing is reported rather than silently
     * dropped — a hero that quietly stops appearing is the hardest kind of
     * regression to notice.
     *
     * @return array{Collection<string, array<string, mixed>>, array<int, string>}
     */
    private function resolveCatalogue(string $sourceDir): array
    {
        $missing = [];
        $present = [];

        foreach (ImageCatalogue::entries() as $entry) {
            $path = $sourceDir.DIRECTORY_SEPARATOR.$entry['file'];

            if (! File::isFile($path)) {
                $missing[] = $entry['file'];

                continue;
            }

            $present[$entry['file']] = $entry + ['path' => $path];
        }

        return [collect($present), $missing];
    }

    /**
     * Downscale each source once into a temp folder.
     *
     * Without this every article re-decodes a full-resolution frame for each of
     * eight conversions. With it the expensive work happens 28 times instead of
     * 712.
     *
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @return Collection<string, string> filename => master path
     */
    private function buildMasters(Collection $catalogue): Collection
    {
        $dir = storage_path('app/seed-masters');
        File::ensureDirectoryExists($dir);

        return $catalogue->map(function (array $entry) use ($dir): string {
            $master = $dir.DIRECTORY_SEPARATOR.pathinfo($entry['file'], PATHINFO_FILENAME).'.jpg';

            if (File::isFile($master)) {
                return $master;
            }

            Image::load($entry['path'])
                ->fit(Fit::Max, self::MASTER_WIDTH, self::MASTER_WIDTH)
                ->quality(88)
                ->save($master);

            return $master;
        });
    }

    /**
     * Generate each source's eight conversions once.
     *
     * Two photographs of the same file are the same eight files, so the work is
     * per image, not per article. The names match what Media Library would have
     * produced, because the records we write claim those conversions exist.
     *
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @param  Collection<string, string>  $masters
     * @return Collection<string, array<string, string>> filename => [conversion => cached path]
     */
    private function buildConversionCache(Collection $catalogue, Collection $masters): Collection
    {
        $root = storage_path('app/seed-conversions');
        File::ensureDirectoryExists($root);

        return $catalogue->map(function (array $entry) use ($masters, $root): array {
            $stem = pathinfo($this->storedFileName($entry['file']), PATHINFO_FILENAME);
            $dir = $root.DIRECTORY_SEPARATOR.$stem;
            File::ensureDirectoryExists($dir);

            $paths = [];

            foreach (MediaConversions::widths() as $name => $width) {
                foreach ([$name => 'webp', $name.MediaConversions::FALLBACK_SUFFIX => 'jpg'] as $conversion => $format) {
                    $path = $dir.DIRECTORY_SEPARATOR
                        .MediaConversions::conversionFileName($this->storedFileName($entry['file']), $conversion);

                    if (! File::isFile($path)) {
                        Image::load($masters[$entry['file']])
                            ->width($width)
                            ->quality($format === 'webp' ? MediaConversions::WEBP_QUALITY : MediaConversions::JPEG_QUALITY)
                            ->save($path);
                    }

                    $paths[$conversion] = $path;
                }
            }

            return $paths;
        });
    }

    /**
     * Decide which image each article gets.
     *
     * The rule that matters is positional, not editorial: an image may appear
     * many times across the site, but never twice inside one homepage section,
     * because a grid showing the same skyline three times reads as broken even
     * when nothing is. So the homepage is resolved first and its sections are
     * filled under that constraint; everything else is filled afterwards.
     *
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @return Collection<int, string> article id => filename
     */
    private function assign(Collection $catalogue): Collection
    {
        $files = $catalogue->keys()->all();
        $assigned = collect();
        $usageCount = array_fill_keys($files, 0);

        foreach ($this->homepageSections() as $articles) {
            $usedHere = [];

            foreach ($articles as $article) {
                $file = $this->pick($catalogue, $article, $usedHere, $usageCount);

                $assigned[$article->getKey()] = $file;
                $usedHere[$file] = true;
                $usageCount[$file]++;
            }
        }

        // Everything not on the front page. No section to clash inside, so the
        // only goal left is spreading the library evenly.
        Article::query()
            ->whereNotNull('hero_media_id')
            ->orderBy('id')
            ->select(['id', 'category_id'])
            ->with('category:id,slug')
            ->chunk(100, function (Collection $chunk) use ($catalogue, &$assigned, &$usageCount): void {
                foreach ($chunk as $article) {
                    if ($assigned->has($article->getKey())) {
                        continue;
                    }

                    $file = $this->pick($catalogue, $article, [], $usageCount);

                    $assigned[$article->getKey()] = $file;
                    $usageCount[$file]++;
                }
            });

        return $assigned;
    }

    /**
     * Sections of the live homepage, each as the list of articles in it.
     *
     * @return Collection<int, Collection<int, Article>>
     */
    private function homepageSections(): Collection
    {
        $layout = HomepageLayout::query()->where('is_active', true)->first();

        if ($layout === null) {
            return collect();
        }

        return app(ComposeHomepage::class)($layout, config('masar.locales.default', 'ar'))
            ->map(fn (array $section): Collection => collect($section['items'])
                ->filter(fn ($item): bool => $item instanceof Article)
                ->values())
            ->filter(fn (Collection $items): bool => $items->isNotEmpty())
            ->values();
    }

    /**
     * The least-used image that suits this article's category and is not
     * already in this section.
     *
     * Affinity is a preference; the no-repeat rule is not. If honouring both is
     * impossible the affinity is what gives way, because an off-topic photo
     * reads as a choice and a duplicated one reads as a bug.
     *
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @param  array<string, true>  $usedHere
     * @param  array<string, int>  $usageCount
     */
    private function pick(Collection $catalogue, Article $article, array $usedHere, array $usageCount): string
    {
        $slug = $article->category?->slug ?? '';
        $wanted = ImageCatalogue::affinity()[$slug] ?? [];

        $available = $catalogue->keys()->reject(fn (string $file): bool => isset($usedHere[$file]));

        // Every image is already in this section — only possible for a section
        // larger than the library, and then a repeat is unavoidable.
        if ($available->isEmpty()) {
            $available = $catalogue->keys();
        }

        $onTopic = $available->filter(
            fn (string $file): bool => array_intersect($wanted, $catalogue[$file]['subjects']) !== []
        );

        $pool = $onTopic->isNotEmpty() ? $onTopic : $available;

        return $pool->sortBy(fn (string $file): int => $usageCount[$file] ?? 0)->first();
    }

    /**
     * Replace the placeholder media rows with real files.
     *
     * The factory writes a media row with no bytes behind it — enough for the
     * gate, useless to a browser. Here the row is rebuilt from an actual file so
     * `getUrl()` resolves and the conversions exist.
     *
     * `hero_alt` is written from the image except where it is already null:
     * DemoContentSeeder nulls it deliberately on one article to demonstrate the
     * gate refusing a hero without alt text, and filling it in here would quietly
     * repair a defect that is meant to be visible.
     *
     * @param  Collection<int, string>  $assignments
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @param  Collection<string, string>  $masters
     * @param  Collection<string, array<string, string>>  $conversions
     */
    private function apply(
        Collection $assignments,
        Collection $catalogue,
        Collection $masters,
        Collection $conversions,
    ): void {
        $bar = $this->command?->getOutput()->createProgressBar($assignments->count());
        $bar?->start();

        Article::query()
            ->whereIn('id', $assignments->keys()->all())
            ->orderBy('id')
            ->chunkById(25, function (Collection $chunk) use ($assignments, $catalogue, $masters, $conversions, $bar): void {
                foreach ($chunk as $article) {
                    $entry = $catalogue[$assignments[$article->getKey()]];

                    $article->clearMediaCollection('hero');

                    // Conversions are copied in below, so nothing regenerates
                    // them here. Suspension and the copy are one unit: the media
                    // row is wrong until both have run.
                    $media = MediaConversions::withoutGenerating(
                        fn (): Media => $article
                            ->addMedia($masters[$entry['file']])
                            ->preservingOriginal()
                            ->usingName(pathinfo($entry['file'], PATHINFO_FILENAME))
                            ->usingFileName($this->storedFileName($entry['file']))
                            ->withCustomProperties([
                                'alt' => $entry['alt'],
                                'credit' => $entry['credit'],
                                'source_file' => $entry['file'],
                                'orientation' => $entry['orientation'],
                            ])
                            ->toMediaCollection('hero')
                    );

                    $this->attachConversions($media, $conversions[$entry['file']]);

                    $article->forceFill([
                        'hero_media_id' => $media->getKey(),
                        'hero_credit' => $entry['credit'],
                    ] + ($article->hero_alt === null ? [] : ['hero_alt' => $entry['alt']]))->save();

                    $this->fillInlineImage($article, $entry, $catalogue, $masters, $conversions);

                    $bar?->advance();
                }
            });

        $bar?->finish();
        $this->command?->newLine();
    }

    /**
     * Give the body's image block a photograph that is not the hero.
     *
     * The same picture twice on one page — once at the top, once halfway down —
     * reads as a mistake even though both are correct. The choice is made here
     * rather than in the factory because the factory has no library to choose
     * from: it writes the block's position, which is editorial, and leaves the
     * picture to whatever library the environment actually has.
     *
     * @param  array<string, mixed>  $heroEntry
     * @param  Collection<string, array<string, mixed>>  $catalogue
     * @param  Collection<string, string>  $masters
     * @param  Collection<string, array<string, string>>  $conversions
     */
    private function fillInlineImage(
        Article $article,
        array $heroEntry,
        Collection $catalogue,
        Collection $masters,
        Collection $conversions,
    ): void {
        $block = $article->blocks()->where('type', 'image')->orderBy('sort_order')->first();

        if ($block === null) {
            return;
        }

        $entry = $catalogue
            ->reject(fn (array $candidate): bool => $candidate['file'] === $heroEntry['file'])
            ->values()
            ->get($article->getKey() % max(1, $catalogue->count() - 1));

        if ($entry === null) {
            return;
        }

        $article->clearMediaCollection('inline');

        $media = MediaConversions::withoutGenerating(
            fn (): Media => $article
                ->addMedia($masters[$entry['file']])
                ->preservingOriginal()
                ->usingName(pathinfo($entry['file'], PATHINFO_FILENAME))
                ->usingFileName($this->storedFileName($entry['file']))
                ->withCustomProperties([
                    'alt' => $entry['alt'],
                    'credit' => $entry['credit'],
                    'source_file' => $entry['file'],
                ])
                ->toMediaCollection('inline')
        );

        $this->attachConversions($media, $conversions[$entry['file']]);

        $block->update(['data' => ['media_id' => $media->getKey()] + ($block->data ?? [])]);
    }

    /**
     * Copy the pre-generated conversions into place and mark them generated.
     *
     * `generated_conversions` is what `hasGeneratedConversion()` reads, and the
     * srcset builder skips anything not listed there — so writing the files
     * without the flags would produce records that render no srcset at all.
     *
     * @param  array<string, string>  $cached
     */
    private function attachConversions(Media $media, array $cached): void
    {
        $disk = Storage::disk($media->conversions_disk);
        $directory = PathGeneratorFactory::create($media)->getPathForConversions($media);
        $flags = [];

        foreach ($cached as $conversion => $path) {
            $handle = fopen($path, 'rb');
            $disk->put($directory.basename($path), $handle);

            if (is_resource($handle)) {
                fclose($handle);
            }

            $flags[$conversion] = true;
        }

        $media->forceFill(['generated_conversions' => $flags])->save();
    }

    /**
     * Stored filenames are ASCII and slugged. The sources include spaces and
     * parentheses, which survive a local disk and then break the first time a
     * URL is signed or a CDN normalises the path.
     */
    private function storedFileName(string $file): string
    {
        return str(pathinfo($file, PATHINFO_FILENAME))->slug()->value().'.jpg';
    }
}
