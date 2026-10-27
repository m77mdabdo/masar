<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

/**
 * ArticlePolicy::publish() and ArticlePolicy::seo() were both written, both
 * documented, and neither was ever called — found by the same sweep that found
 * factCheck. These tests drive the panel, not the policy: a policy unit test
 * passes just as happily when nothing invokes the policy, which is how all
 * three survived.
 *
 * The `seo` role is the one that matters for publish. It carries
 * article.update.any, so an SEO specialist can open any article at any status
 * — including one sitting at ready with a clean gate — and has no publish
 * permission. That is the user who was shown a live publish button.
 */
it('disables the publish button for a user who may edit but may not publish', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    $this->actingAs(staff('seo'));

    expect($article->isPublishable())->toBe([]);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertActionDisabled(TestAction::make('publish'));
});

it('enables the publish button for an editor once the gate is clean', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    $this->actingAs(staff('editor'));

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertActionEnabled(TestAction::make('publish'));
});

it('still disables the publish button for an editor while the gate fails', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Ready,
        'why_it_matters' => null,
    ]);

    $this->actingAs(staff('editor'));

    expect($article->isPublishable())->not->toBe([]);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertActionDisabled(TestAction::make('publish'));
});

it('hides the SEO tab from a writer on their own draft', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create([
        'author_id' => $writer->id,
        'status' => ArticleStatus::Writing,
    ]);

    $this->actingAs($writer);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertSchemaComponentHidden('meta_title')
        ->assertSchemaComponentHidden('noindex');
});

it('shows the SEO tab to a user who holds the permission', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    $this->actingAs(staff('seo'));

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertSchemaComponentVisible('meta_title')
        ->assertSchemaComponentVisible('noindex');
});

/**
 * The reason the tab is hidden rather than disabled. noindex removes an article
 * from search and leaves no trace on the page, so the field must not be
 * writable by someone the panel never showed it to.
 */
it('refuses a noindex written by a writer whose form never carried the field', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create([
        'author_id' => $writer->id,
        'status' => ArticleStatus::Writing,
        'noindex' => false,
    ]);

    $this->actingAs($writer);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->set('data.noindex', true)
        ->call('save');

    expect($article->fresh()->noindex)->toBeFalse();
});

it('hides the SEO tab on create from a user without the permission', function (): void {
    $this->actingAs(staff('writer'));

    livewire(CreateArticle::class)
        ->assertSchemaComponentHidden('meta_title');
});
