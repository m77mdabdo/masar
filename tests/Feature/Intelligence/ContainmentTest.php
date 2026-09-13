<?php

declare(strict_types=1);

use App\Actions\Intelligence\StoreSourceItem;
use App\Enums\SourceLegalMode;
use App\Models\IntelligenceItem;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Intelligence\ParsedItem;
use Illuminate\Support\Facades\Http;

/**
 * CLAUDE.md §5: raw text ingested from monitored sources is internal only and
 * must never render on the public site.
 *
 * These assert the mechanism as well as the outcome. "No public page currently
 * shows it" is a fact about today's templates; "no public controller can reach
 * the model" is a fact about the code.
 */
it('stores no body text for a source whose terms do not allow it', function (): void {
    $source = Source::factory()->create(['legal_mode' => SourceLegalMode::Metadata]);

    app(StoreSourceItem::class)($source, new ParsedItem(
        title: 'عنوان',
        url: 'https://example.test/a',
        summary: 'ملخص الناشر',
        body: 'النص الكامل للناشر الذي لا يحق لنا تخزينه.',
    ));

    $stored = SourceItem::query()->firstOrFail();

    expect($stored->raw_body)->toBeNull()
        // Nor does their summary become ours by being copied into the record
        // an editor reads.
        ->and(IntelligenceItem::query()->firstOrFail()->summary)->toBeNull();
});

it('stores body text only where the source permits it', function (): void {
    $source = Source::factory()->retainingBody()->create();

    app(StoreSourceItem::class)($source, new ParsedItem(
        title: 'عنوان',
        url: 'https://example.test/b',
        summary: 'ملخص',
        body: 'النص الكامل.',
    ));

    expect(SourceItem::query()->firstOrFail()->raw_body)->toBe('النص الكامل.');
});

it('hides ingested body text from every array and json cast of the model', function (): void {
    $item = SourceItem::factory()->create(['raw_body' => 'نص الناشر']);

    // The failure this guards is somebody returning the model instead of a
    // field of it — which reads as ordinary code and leaks the publisher's
    // prose into a response.
    expect($item->toArray())->not->toHaveKey('raw_body')
        ->and($item->toArray())->not->toHaveKey('payload')
        ->and(json_encode($item))->not->toContain('نص الناشر');
});

it('lets no public controller reach an ingested model', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Http/Controllers/Web')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());

        if (preg_match('/\b(SourceItem|IntelligenceItem)\b/', $code)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('renders no ingested model in any public view', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/\b(raw_body|SourceItem|IntelligenceItem)\b/', (string) file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('never exposes an ingested item on a public route', function (): void {
    $source = Source::factory()->retainingBody()->create();

    Http::fake();

    app(StoreSourceItem::class)($source, new ParsedItem(
        title: 'عنوان-مصدر-داخلي',
        url: 'https://publisher.test/internal-only',
        body: 'نص-الناشر-المميز',
    ));

    // Search is the surface most likely to surface something it should not.
    // The query itself is echoed back by the page, so the assertion is on the
    // publisher's body and on their URL — the two things that would actually
    // constitute a leak.
    $html = $this->get('/ar/search?q='.urlencode('عنوان-مصدر-داخلي'))->assertOk()->getContent();

    expect($html)->not->toContain('نص-الناشر-المميز')
        ->and($html)->not->toContain('publisher.test/internal-only');

    // And the homepage, which composes from every content query there is.
    expect($this->get('/ar')->getContent())->not->toContain('نص-الناشر-المميز');
});

it('purges ingested body text at the retention window and keeps our own record', function (): void {
    $source = Source::factory()->retainingBody()->create();

    $old = SourceItem::factory()->create([
        'source_id' => $source->getKey(),
        'raw_body' => 'نص قديم',
        'fetched_at' => now()->subDays(31),
    ]);
    $recent = SourceItem::factory()->create([
        'source_id' => $source->getKey(),
        'raw_body' => 'نص حديث',
        'fetched_at' => now()->subDays(2),
    ]);

    $this->artisan('masar:purge-intelligence')->assertSuccessful();

    expect($old->refresh()->raw_body)->toBeNull()
        ->and($recent->refresh()->raw_body)->toBe('نص حديث')
        // The row itself survives: it is still our evidence that we saw the
        // story, and that record is ours.
        ->and($old->exists)->toBeTrue();
});

it('purges a dismissed item once it is past the recovery window', function (): void {
    $recoverable = IntelligenceItem::factory()->dismissed(10)->create();
    $expired = IntelligenceItem::factory()->dismissed(40)->create();

    $this->artisan('masar:purge-intelligence')->assertSuccessful();

    expect(IntelligenceItem::query()->whereKey($recoverable->getKey())->exists())->toBeTrue()
        ->and(IntelligenceItem::query()->whereKey($expired->getKey())->exists())->toBeFalse();
});
