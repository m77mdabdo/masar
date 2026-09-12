<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Filament\Pages\EditorialPipeline;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

use function Pest\Livewire\livewire;

it('hides another writer\'s article from the list', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id]);
    $other = Article::factory()->create();

    $this->actingAs($writer);

    livewire(ListArticles::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows the whole newsroom to an editor', function (): void {
    $editor = staff('editor');
    $a = Article::factory()->create();
    $b = Article::factory()->create();

    $this->actingAs($editor);

    livewire(ListArticles::class)->assertCanSeeTableRecords([$a, $b]);
});

it('refuses to open another writer\'s article', function (): void {
    $writer = staff('writer');
    $other = Article::factory()->create();

    $this->actingAs($writer);

    // The resource query excludes it, so the record cannot even be resolved.
    expect(ArticleResource::getEloquentQuery()->find($other->getKey()))->toBeNull();
});

it('lets a writer open their own article', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id, 'status' => ArticleStatus::Writing]);

    $this->actingAs($writer);

    livewire(EditArticle::class, ['record' => $own->getKey()])->assertOk();
});

it('offers no transition buttons on another writer\'s article', function (): void {
    $writer = staff('writer');
    $other = Article::factory()->create(['status' => ArticleStatus::Writing]);

    $this->actingAs($writer);

    // The policy denies the transition, so the Action refuses even if invoked.
    expect($writer->can('transition', $other))->toBeFalse();
});

it('stops a writer publishing even when the gate passes', function (): void {
    $writer = staff('writer');
    $article = publishableArticle(['author_id' => $writer->id, 'status' => ArticleStatus::Ready]);

    $this->actingAs($writer);

    // The gate is satisfied; the permission is not. These are separate failures
    // and the Action reports the right one.
    expect($article->isPublishable())->toBe([])
        ->and($writer->can('publish', $article))->toBeFalse();

    expect(fn () => app(TransitionArticleStatus::class)(
        $article,
        ArticleStatus::Published,
        $writer,
    ))->toThrow(AuthorizationException::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Ready);
});

it('keeps another writer\'s cards off the pipeline board', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id, 'status' => ArticleStatus::Writing]);
    Article::factory()->create(['status' => ArticleStatus::Writing]);

    $this->actingAs($writer);

    $board = livewire(EditorialPipeline::class)->instance()->board();
    $ids = collect($board)->flatten(1)->pluck('id');

    expect($ids)->toContain($own->id)->and($ids)->toHaveCount(1);
});

it('denies panel access to a user with no editorial permission', function (): void {
    $outsider = User::factory()->create();

    expect($outsider->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('allows panel access to any staff member who can view articles', function (): void {
    expect(staff('designer')->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
});
