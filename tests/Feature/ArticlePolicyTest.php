<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;

it('lets a writer update their own article while it is still theirs', function (): void {
    $writer = staff('writer');

    foreach ([ArticleStatus::Idea, ArticleStatus::Writing, ArticleStatus::NeedsRevision] as $status) {
        $article = Article::factory()->create(['author_id' => $writer->id, 'status' => $status]);

        expect($writer->can('update', $article))->toBeTrue("should be editable at {$status->value}");
    }
});

it('stops a writer updating their own article once it reaches editor review', function (): void {
    $writer = staff('writer');

    foreach ([ArticleStatus::EditorReview, ArticleStatus::Seo, ArticleStatus::Ready, ArticleStatus::Published] as $status) {
        $article = Article::factory()->create(['author_id' => $writer->id, 'status' => $status]);

        expect($writer->can('update', $article))->toBeFalse("should be locked at {$status->value}");
    }
});

it('stops a writer updating someone else\'s article', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    expect($writer->can('update', $article))->toBeFalse();
});

it('lets an editor update any article at any stage', function (): void {
    $editor = staff('editor');
    $article = Article::factory()->create(['status' => ArticleStatus::Published]);

    expect($editor->can('update', $article))->toBeTrue();
});

it('treats a credited co-author as an owner', function (): void {
    $writer = staff('writer');
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);
    $article->coAuthors()->attach($writer->id, ['role' => 'reporting']);

    expect($writer->can('update', $article->fresh()))->toBeTrue();
});

it('forbids an author from fact checking their own work', function (): void {
    // Editorial rule, not a permission question: this user *has* the permission.
    $checker = staff('fact_checker');
    $own = Article::factory()->create(['author_id' => $checker->id]);

    expect($checker->can('article.factcheck'))->toBeTrue()
        ->and($checker->can('factCheck', $own))->toBeFalse();
});

it('lets a fact checker check other people\'s work', function (): void {
    $checker = staff('fact_checker');
    $article = Article::factory()->create();

    expect($checker->can('factCheck', $article))->toBeTrue();
});

it('forbids fact checking by someone credited as a co-author', function (): void {
    $checker = staff('fact_checker');
    $article = Article::factory()->create();
    $article->coAuthors()->attach($checker->id, ['role' => 'reporting']);

    expect($checker->can('factCheck', $article->fresh()))->toBeFalse();
});

it('requires both the permission and a clean gate to publish', function (): void {
    $editor = staff('editor');

    $incomplete = Article::factory()->create(['summary' => null, 'status' => ArticleStatus::Ready]);
    $complete = publishableArticle(['status' => ArticleStatus::Ready]);

    expect($editor->can('publish', $incomplete))->toBeFalse()
        ->and($editor->can('publish', $complete))->toBeTrue();
});

it('refuses publish rights to a role without the permission', function (): void {
    $writer = staff('writer');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    expect($writer->can('article.publish'))->toBeFalse()
        ->and($writer->can('publish', $article))->toBeFalse();
});

it('lets a writer transition only their own article', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id]);
    $other = Article::factory()->create();

    expect($writer->can('transition', $own))->toBeTrue()
        ->and($writer->can('transition', $other))->toBeFalse();
});

it('gives the super admin every ability', function (): void {
    $admin = staff('super_admin');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    expect($admin->can('update', $article))->toBeTrue()
        ->and($admin->can('delete', $article))->toBeTrue()
        ->and($admin->can('transition', $article))->toBeTrue()
        ->and($admin->can('publish', $article))->toBeTrue()
        ->and($admin->can('seo', $article))->toBeTrue();
});

it('refuses a designer any editorial ability', function (): void {
    $designer = staff('designer');
    $article = Article::factory()->create();

    expect($designer->can('update', $article))->toBeFalse()
        ->and($designer->can('transition', $article))->toBeFalse()
        ->and($designer->can('publish', $article))->toBeFalse();
});
