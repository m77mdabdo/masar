<?php

declare(strict_types=1);

use App\Enums\DuplicateStage;
use App\Models\IntelligenceItem;
use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Intelligence\Deduper;
use App\Services\Intelligence\SimHash;
use App\Services\Intelligence\UrlNormaliser;

function storedItem(Source $source, array $attributes = []): IntelligenceItem
{
    $sourceItem = SourceItem::factory()->create(['source_id' => $source->getKey()] + $attributes);

    return IntelligenceItem::factory()->create([
        'source_item_id' => $sourceItem->getKey(),
        'source_id' => $source->getKey(),
        'detected_at' => now(),
    ]);
}

function candidate(Source $source, array $attributes = []): SourceItem
{
    return SourceItem::factory()->make(['source_id' => $source->getKey()] + $attributes);
}

it('matches on an exact url', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);

    $existing = storedItem($source, [
        'url' => 'https://example.test/a',
        'url_hash' => $urls->hash('https://example.test/a'),
    ]);

    $match = app(Deduper::class)->find(candidate($source, [
        'url' => 'https://example.test/a',
        'url_hash' => $urls->hash('https://example.test/a'),
    ]));

    expect($match['stage'])->toBe(DuplicateStage::Url)
        ->and($match['item']->id)->toBe($existing->id);
});

it('normalises away tracking parameters before the url check', function (): void {
    $urls = app(UrlNormaliser::class);

    // The whole reason stage one does any work: the same article arrives from
    // three places wearing three different campaign parameters.
    expect($urls->hash('https://example.test/a?utm_source=rss&utm_medium=feed'))
        ->toBe($urls->hash('https://www.example.test/a/'));
});

it('matches a canonical url against another item plain url', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);

    $existing = storedItem($source, [
        'url' => 'https://example.test/canonical',
        'url_hash' => $urls->hash('https://example.test/canonical'),
    ]);

    $match = app(Deduper::class)->find(candidate($source, [
        'url' => 'https://syndicated.test/copy',
        'url_hash' => $urls->hash('https://syndicated.test/copy'),
        'canonical_url' => 'https://example.test/canonical',
        'canonical_hash' => $urls->hash('https://example.test/canonical'),
    ]));

    expect($match['stage'])->toBe(DuplicateStage::CanonicalUrl)
        ->and($match['item']->id)->toBe($existing->id);
});

it('matches on the publisher external id within one source', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);

    $existing = storedItem($source, [
        'external_id' => 'GOV-1',
        'url' => 'https://example.test/one',
        'url_hash' => $urls->hash('https://example.test/one'),
    ]);

    $match = app(Deduper::class)->find(candidate($source, [
        'external_id' => 'GOV-1',
        'url' => 'https://example.test/two',
        'url_hash' => $urls->hash('https://example.test/two'),
    ]));

    expect($match['stage'])->toBe(DuplicateStage::ExternalId)
        ->and($match['item']->id)->toBe($existing->id);
});

it('does not match an external id across two different publishers', function (): void {
    $urls = app(UrlNormaliser::class);
    $one = Source::factory()->create();
    $two = Source::factory()->create();

    storedItem($one, ['external_id' => '1', 'url' => 'https://a.test/x', 'url_hash' => $urls->hash('https://a.test/x')]);

    // Two publishers' id sequences share a namespace by accident, never by
    // meaning.
    $match = app(Deduper::class)->find(candidate($two, [
        'external_id' => '1',
        'url' => 'https://b.test/y',
        'url_hash' => $urls->hash('https://b.test/y'),
        'title_simhash' => app(SimHash::class)('عنوان مختلف تمامًا عن الأول في كل شيء'),
    ]));

    expect($match)->toBeNull();
});

it('flags a near-duplicate title rather than discarding it', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);
    $simhash = app(SimHash::class);

    $original = 'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج';
    $rewrite = 'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح';

    $existing = storedItem($source, [
        'raw_title' => $original,
        'title_simhash' => $simhash($original),
        'url' => 'https://example.test/original',
        'url_hash' => $urls->hash('https://example.test/original'),
    ]);

    $match = app(Deduper::class)->find(candidate($source, [
        'raw_title' => $rewrite,
        'title_simhash' => $simhash($rewrite),
        'url' => 'https://example.test/rewrite',
        'url_hash' => $urls->hash('https://example.test/rewrite'),
    ]));

    expect($match['stage'])->toBe(DuplicateStage::Title)
        ->and($match['item']->id)->toBe($existing->id)
        // The point of the whole design: found, flagged, still here. A second
        // development on a story is often the actual news.
        ->and($match['stage']->isCertain())->toBeFalse();
});

it('does not match two different stories from the same publisher', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);
    $simhash = app(SimHash::class);

    $a = 'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج';
    $b = 'هيئة السوق المالية تعلن نتائج الربع الثالث للسوق الموازية';

    storedItem($source, [
        'raw_title' => $a,
        'title_simhash' => $simhash($a),
        'url' => 'https://example.test/a',
        'url_hash' => $urls->hash('https://example.test/a'),
    ]);

    $match = app(Deduper::class)->find(candidate($source, [
        'raw_title' => $b,
        'title_simhash' => $simhash($b),
        'url' => 'https://example.test/b',
        'url_hash' => $urls->hash('https://example.test/b'),
    ]));

    expect($match)->toBeNull();
});

it('ignores an item outside the dedup window', function (): void {
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);

    $old = storedItem($source, ['url' => 'https://example.test/a', 'url_hash' => $urls->hash('https://example.test/a')]);
    $old->forceFill(['detected_at' => now()->subDays(30)])->save();

    // A headline recurring months later is a new story, not a duplicate.
    $match = app(Deduper::class)->find(candidate($source, [
        'url' => 'https://example.test/a',
        'url_hash' => $urls->hash('https://example.test/a'),
    ]));

    expect($match)->toBeNull();
});

/**
 * The distribution the threshold was calibrated against.
 *
 * SimHash's failure mode is silence — it keeps returning numbers whatever it is
 * doing — so this pins the shape rather than the outcome. A change to the
 * Arabic normaliser that shifts these bands has to fail here, loudly, rather
 * than quietly stop deduplicating or start merging unrelated stories.
 */
it('keeps the simhash distribution the threshold was calibrated against', function (array $case): void {
    [$label, $a, $b, $min, $max] = $case;
    $simhash = app(SimHash::class);

    $distance = $simhash->distance($simhash($a), $simhash($b));

    expect($distance)->toBeGreaterThanOrEqual($min, $label)
        ->and($distance)->toBeLessThanOrEqual($max, $label);
})->with([
    'identical' => [['identical',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 0, 0]],
    'diacritics folded' => [['diacritics',
        'هيئة السُّوق الماليَّة تعتمد تعديلات على لوائح الطرح',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح', 0, 0]],
    'word order' => [['reordered',
        'تعتمد هيئة السوق المالية تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 0, 0]],
    'near: one word added' => [['one word',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 1, 16]],
    'near: breaking prefix' => [['prefix',
        'عاجل: هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 1, 16]],
    'different: same publisher' => [['same publisher',
        'هيئة السوق المالية تعلن نتائج الربع الثالث للسوق الموازية',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 17, 64]],
    'different: unrelated' => [['unrelated',
        'وزارة الصناعة تعلن جولة تراخيص جديدة للتعدين الاستكشافي',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 17, 64]],
]);

it('separates near-duplicates from different stories at the configured threshold', function (): void {
    // The threshold is only meaningful if it sits inside the gap. If someone
    // changes it to the literature's 3, this fails and says why.
    expect((int) config('masar.intelligence.dedup.simhash_max_distance'))
        ->toBeGreaterThanOrEqual(14)
        ->toBeLessThan(17);
});
