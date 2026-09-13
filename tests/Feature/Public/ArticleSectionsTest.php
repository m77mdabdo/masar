<?php

declare(strict_types=1);

use App\Actions\Articles\SyncArticleBlocks;
use App\Enums\ArticleSection;
use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Models\Article;
use App\Models\Category;

/**
 * The four questions are the article's structure, not a summary above a second
 * copy of it. These lock that down in both directions: the sectioned shape and
 * the stream an opinion piece gets instead.
 */
function sectionedArticle(array $sections, array $attributes = []): Article
{
    $article = Article::factory()->withHeroImage()->withSources()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => Category::query()->firstOrCreate(
            ['slug' => 'saudi'],
            ['is_active' => true, 'sort_order' => 1, 'color' => '#0E2A1C'],
        )->id,
        ...$attributes,
    ]);

    $order = 0;

    foreach ($sections as $section => $blocks) {
        foreach ($blocks as $block) {
            $article->blocks()->create($block + ['section' => $section, 'sort_order' => $order++]);
        }
    }

    return $article->refresh();
}

it('renders the four questions as the article when blocks carry a section', function (): void {
    $article = sectionedArticle([
        ArticleSection::WhatHappened->value => [['type' => 'paragraph', 'data' => ['text' => 'وقع الحدث.']]],
        ArticleSection::WhyItMatters->value => [['type' => 'paragraph', 'data' => ['text' => 'وهذا مهم.']]],
    ]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    expect($html)->toContain('id="s-what_happened"')
        ->and($html)->toContain('id="s-why_it_matters"');
});

it('renders a plain stream when no block carries a section', function (): void {
    $article = sectionedArticle([], ['content_type' => ContentType::Opinion]);
    $article->blocks()->create(['type' => 'paragraph', 'data' => ['text' => 'رأي.'], 'sort_order' => 0]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    // Four headings over a paragraph each would be a lie about how the piece is
    // written; the four answers follow it as a band instead.
    expect($html)->not->toContain('id="s-what_happened"')
        ->and($html)->toContain('رأي.');
});

it('keeps a gate-required answer on the page in both shapes', function (): void {
    // `why_it_matters` is a publish gate rule. A shape that renders the blocks
    // but drops the column would let an editor satisfy the gate with text no
    // reader ever sees.
    $sectioned = sectionedArticle(
        [ArticleSection::WhyItMatters->value => [['type' => 'paragraph', 'data' => ['text' => 'تفصيل.']]]],
        ['why_it_matters' => 'الإجابة المطلوبة للبوابة.'],
    );

    $this->get("/ar/saudi/{$sectioned->slug}")->assertOk()->assertSee('الإجابة المطلوبة للبوابة.', false);

    $flat = sectionedArticle([], ['why_it_matters' => 'إجابة المقال الحر.']);
    $flat->blocks()->create(['type' => 'paragraph', 'data' => ['text' => 'نص.'], 'sort_order' => 0]);

    $this->get("/ar/saudi/{$flat->slug}")->assertOk()->assertSee('إجابة المقال الحر.', false);
});

it('puts a section heading block into the subhead rather than the body', function (): void {
    $article = sectionedArticle([
        ArticleSection::WhatHappened->value => [
            ['type' => 'heading', 'data' => ['level' => 3, 'text' => 'عنوان القسم']],
            ['type' => 'paragraph', 'data' => ['text' => 'المتن.']],
        ],
    ]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    // Once, as the section's h2 — not once as a subhead and again as a heading.
    expect(substr_count($html, 'عنوان القسم'))->toBe(1);
});

it('renders each section in the order the four questions are asked', function (): void {
    $article = sectionedArticle([
        // Deliberately inserted out of order: the page orders by the enum, not
        // by whichever section an editor happened to fill first.
        ArticleSection::Opportunity->value => [['type' => 'paragraph', 'data' => ['text' => 'فرصة.']]],
        ArticleSection::WhatHappened->value => [['type' => 'paragraph', 'data' => ['text' => 'حدث.']]],
    ]);

    $html = $this->get("/ar/saudi/{$article->slug}")->assertOk()->getContent();

    expect(strpos($html, 'id="s-what_happened"'))->toBeLessThan(strpos($html, 'id="s-opportunity"'));
});

it('round-trips a block section through the builder state and back', function (): void {
    $article = sectionedArticle([]);

    // What the Filament Builder hands back: the section travels inside the
    // block's form state and is lifted to its column by the Action.
    app(SyncArticleBlocks::class)($article, [
        ['type' => 'paragraph', 'data' => ['section' => 'why_it_matters', 'text' => 'نص.']],
        ['type' => 'paragraph', 'data' => ['section' => null, 'text' => 'حر.']],
    ]);

    $blocks = $article->refresh()->blocks;

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]->section)->toBe(ArticleSection::WhyItMatters)
        ->and($blocks[0]->data)->not->toHaveKey('section')
        ->and($blocks[1]->section)->toBeNull();

    $state = SyncArticleBlocks::toFormState($article->refresh());

    expect($state[0]['data']['section'])->toBe('why_it_matters')
        ->and($state[1]['data']['section'])->toBeNull();
});

it('ignores a section value that is not one of the four', function (): void {
    $article = sectionedArticle([]);

    app(SyncArticleBlocks::class)($article, [
        ['type' => 'paragraph', 'data' => ['section' => 'somewhere_else', 'text' => 'نص.']],
    ]);

    expect($article->refresh()->blocks->first()->section)->toBeNull();
});
