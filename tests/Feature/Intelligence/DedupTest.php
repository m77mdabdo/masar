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

    // Measures 5. The pair this test used to carry — the same headline with
    // "والإدراج" dropped — measures 14 and no longer matches at a threshold of
    // 10. That is a real miss, and it is recorded as such in the overlap test
    // below rather than papered over by widening the threshold.
    $original = 'الهيئة تقر ضوابط جديدة لإدراج الشركات';
    $rewrite = 'عاجل: الهيئة تقر ضوابط جديدة لإدراج الشركات';

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
 * The measured distribution — exact values, not bands.
 *
 * SimHash's failure mode is silence: it keeps returning numbers whatever it is
 * doing. The previous version of this test pinned wide bands (1-16, 17-64),
 * which is loose enough to pass whatever the normaliser does, and it was the
 * reason a calibration nobody had re-checked survived.
 *
 * Every pair below shares the stem "هيئة السوق المالية", which is exactly what
 * made the original calibration look clean: shared tokens hold the distances
 * apart. These numbers are real and reproducible. They are also not
 * representative — see the overlap test further down, which uses pairs that do
 * not share a stem and where the classes cross over.
 */
it('pins the simhash distances of stem-sharing pairs exactly', function (array $case): void {
    [$label, $a, $b, $expected] = $case;
    $simhash = app(SimHash::class);

    $distance = $simhash->distance($simhash($a), $simhash($b));

    expect($distance)->toBe($expected, $label);
})->with([
    'identical' => [['identical',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 0]],
    'diacritics folded' => [['diacritics',
        'هيئة السُّوق الماليَّة تعتمد تعديلات على لوائح الطرح',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح', 0]],
    'word order' => [['reordered',
        'تعتمد هيئة السوق المالية تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 0]],
    'near: one word added' => [['one word',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 14]],
    'near: breaking prefix' => [['prefix',
        'عاجل: هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 11]],
    'different: same publisher' => [['same publisher',
        'هيئة السوق المالية تعلن نتائج الربع الثالث للسوق الموازية',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 25]],
    'different: unrelated' => [['unrelated',
        'وزارة الصناعة تعلن جولة تراخيص جديدة للتعدين الاستكشافي',
        'هيئة السوق المالية تعتمد تعديلات على لوائح الطرح والإدراج', 30]],
]);

it('holds the threshold at the calibrated value', function (): void {
    // Not a range "inside the gap" — see the next test, there is no gap. This
    // pins the one value the overlap was measured against, so moving it is a
    // deliberate act with a test to update rather than a quiet retune.
    expect((int) config('masar.intelligence.dedup.simhash_max_distance'))->toBe(10);
});

it('records that simhash does not separate same-story from different-story headlines', function (): void {
    // This test exists to stop the gap being re-invented. An earlier comment in
    // config/masar.php claimed unrelated stories measured 26-33 and put the
    // threshold at 16 between the classes. Measured against real headlines the
    // classes overlap, and both of these pairs are the wrong side of any line.
    $sim = app(SimHash::class);

    // Same sentence, different company. Two stories — and closer together than
    // most genuine rewrites.
    $differentStory = $sim->distance(
        $sim('أرامكو تعلن نتائج الربع الثالث'),
        $sim('سابك تعلن نتائج الربع الثالث'),
    );

    // Same story, paraphrased subject. Further apart than many unrelated pairs.
    $sameStory = $sim->distance(
        $sim('البنك المركزي يبقي على سعر الفائدة دون تغيير'),
        $sim('ساما تبقي سعر الفائدة دون تغيير'),
    );

    expect($differentStory)->toBeLessThan($sameStory,
        'the classes overlap: a different story scores closer than a genuine rewrite');

    // And concretely, so a change to the normaliser that shifts the scale fails
    // here and says why.
    expect($differentStory)->toBe(7)
        ->and($sameStory)->toBe(20);
});

it('flags rather than discards, which is what makes the overlap survivable', function (): void {
    // With no clean separation, both error types happen. Flagging keeps the
    // item either way; a wrong flag costs an editor a keystroke, where a wrong
    // discard would hide a real story. The Deduper's whole contract is that it
    // reports a match — it never removes anything.
    $source = Source::factory()->create();
    $urls = app(UrlNormaliser::class);
    $simhash = app(SimHash::class);

    $title = 'الهيئة تقر ضوابط جديدة لإدراج الشركات';

    $existing = storedItem($source, [
        'raw_title' => $title,
        'title_simhash' => $simhash($title),
        'url' => 'https://example.test/a',
        'url_hash' => $urls->hash('https://example.test/a'),
    ]);

    $before = IntelligenceItem::query()->count();

    $match = app(Deduper::class)->find(candidate($source, [
        'raw_title' => 'عاجل: '.$title,
        'title_simhash' => $simhash('عاجل: '.$title),
        'url' => 'https://example.test/b',
        'url_hash' => $urls->hash('https://example.test/b'),
    ]));

    expect($match['stage'])->toBe(DuplicateStage::Title)
        ->and($match['item']->id)->toBe($existing->id)
        // Nothing was removed on the way to that answer.
        ->and(IntelligenceItem::query()->count())->toBe($before)
        ->and($match['stage']->isCertain())->toBeFalse();
});
