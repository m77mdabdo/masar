<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Models\Company;
use App\Models\HomepageLayout;
use App\Models\Opportunity;
use App\Support\ArabicNormaliser;
use App\Support\EntityUrl;
use Database\Seeders\ContentTranslationsSeeder;
use Illuminate\Http\UploadedFile;

/*
 * A page in any edition has the same parts as its Arabic counterpart. Found by
 * comparing the skeleton of 810 pages against Arabic (2026-10-29): translated
 * stories lost their inline images and video, entity pages outside Arabic lost
 * the opportunities that mention them, and Hindi search matched nearly
 * everything. Each test below fails on the code as it was.
 */

beforeEach(function (): void {
    HomepageLayout::factory()->create(['is_active' => true]);
    config()->set('masar.locales.en.enabled', true);
    config()->set('masar.locales.hi.enabled', true);
});

function translationOf(Article $source, string $locale): Article
{
    $copy = Article::factory()->published()->create([
        'locale' => $locale, 'translation_group_id' => $source->translation_group_id, 'source_article_id' => $source->id,
        'translation_status' => TranslationStatus::OwnerAuthorized, 'category_id' => $source->category_id,
        'hero_media_id' => $source->hero_media_id, 'slug' => $source->slug, 'published_at' => $source->published_at,
    ]);
    foreach ($source->blocks as $block) {
        $copy->blocks()->create($block->only(['type', 'data', 'section', 'sort_order']));
    }

    return $copy->fresh(['blocks', 'category']);
}

it('shows a translated story the original’s inline images', function (): void {
    $source = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()->subDay(), 'locale' => 'ar']);
    $media = $source->addMedia(UploadedFile::fake()->image('inline.jpg', 1200, 800))->toMediaCollection('inline');
    $source->blocks()->create(['type' => 'image', 'data' => ['media_id' => $media->id, 'caption' => 'تعليق'], 'sort_order' => 2]);
    $copy = translationOf($source->fresh('blocks'), 'en');
    $urls = app(EntityUrl::class);

    $images = fn (string $html): int => preg_match('#<main.*</main>#s', $html, $m) ? substr_count($m[0], '<img') : 0;
    $ar = $this->get($urls->for($source->fresh('category'), 'ar'))->assertOk()->getContent();
    $en = $this->get($urls->for($copy, 'en'))->assertOk()->getContent();

    expect($copy->mediaOwner()->is($source))->toBeTrue()
        ->and($en)->toContain($media->file_name === '' ? 'inline' : pathinfo($media->file_name, PATHINFO_FILENAME))
        ->and($images($en))->toBe($images($ar));
});

it('gives a translated opportunity the original’s entity mentions, so entity pages list it in every edition', function (): void {
    $company = Company::factory()->create();
    $original = Opportunity::factory()->create(['locale' => 'ar', 'published_at' => now()->subDay(), 'deadline' => now()->addMonth()]);
    $original->mentions()->create(['entity_type' => $company->getMorphClass(), 'entity_id' => $company->id, 'role' => 'primary', 'prominence' => 80]);
    $copy = Opportunity::factory()->create(['locale' => 'en', 'translation_group_id' => $original->translation_group_id,
        'published_at' => $original->published_at, 'deadline' => $original->deadline]);

    // The repair stage runs with any dictionary, and twice is the same as once.
    (new ContentTranslationsSeeder)->withDictionaries(['en' => []])->run();
    (new ContentTranslationsSeeder)->withDictionaries(['en' => []])->run();

    expect($copy->mentions()->where('entity_id', $company->id)->count())->toBe(1);
});

it('keeps a Hindi word whole when folding a search, so it matches like any other edition', function (): void {
    // Devanagari vowel signs and the nukta are combining marks; splitting on
    // them turned one word into single letters, each matched on its own.
    expect(app(ArabicNormaliser::class)('ज़ैज़फ़ून सैंड्स'))->toBe('ज़ैज़फ़ून सैंड्स')
        ->and(app(ArabicNormaliser::class)('الاستثمارُ الأجنبيّ'))->toBe('استثمار اجنبي');

    $hit = Article::factory()->published()->create(['locale' => 'hi', 'title' => 'ज़ैज़फ़ून सैंड्स का पहला संयंत्र']);
    Article::factory()->published()->create(['locale' => 'hi', 'title' => 'जल फल नल']); // the letters, not the word

    expect(app(\App\Queries\SearchArticlesQuery::class)('ज़ैज़फ़ून', 'hi')->pluck('id')->all())->toBe([$hit->id]);
});
