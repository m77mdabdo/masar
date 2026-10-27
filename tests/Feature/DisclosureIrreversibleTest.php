<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Exceptions\DisclosureCannotBeWithdrawn;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;

use function Pest\Livewire\livewire;

/**
 * The advertise page says the sponsored label cannot be removed after
 * publication. That is a claim of prevention, so it owes these tests.
 */
it('refuses to clear the sponsored flag on a published article', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'is_sponsored' => true,
        'sponsor_name' => 'شركة المراعي',
    ]);

    expect(fn () => $article->update(['is_sponsored' => false]))
        ->toThrow(DisclosureCannotBeWithdrawn::class);

    expect($article->fresh()->is_sponsored)->toBeTrue();
});

it('allows the flag to be cleared once the article leaves publication', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'is_sponsored' => true,
        'sponsor_name' => 'شركة المراعي',
    ]);

    // The way back is deliberate and visible: unpublish, then unmark.
    $article->forceFill(['status' => ArticleStatus::Archived])->save();
    $article->update(['is_sponsored' => false]);

    expect($article->fresh()->is_sponsored)->toBeFalse();
});

it('leaves an unpublished sponsored article editable', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Writing,
        'is_sponsored' => true,
        'sponsor_name' => 'شركة المراعي',
    ]);

    $article->update(['is_sponsored' => false]);

    expect($article->fresh()->is_sponsored)->toBeFalse();
});

it('does not fire when the flag is being set rather than cleared', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'is_sponsored' => false,
    ]);

    $article->update(['is_sponsored' => true, 'sponsor_name' => 'بنك الرياض']);

    expect($article->fresh()->is_sponsored)->toBeTrue();
});

it('shows the editor the reason instead of an exception', function (): void {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'is_sponsored' => true,
        'sponsor_name' => 'شركة المراعي',
    ]);

    $this->actingAs(staff('editor_in_chief'));

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertSee('لا يمكن إزالة الوسم عن مادة منشورة');
});
