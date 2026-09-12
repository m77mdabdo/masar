<?php

declare(strict_types=1);

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleTopics;
use App\Enums\ArticleStatus;
use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Company;
use App\Models\Person;
use App\Models\Topic;
use App\Queries\EntityContentQuery;

function syncEntities(): SyncArticleEntities
{
    return app(SyncArticleEntities::class);
}

it('attaches mentions and sets the counters', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();
    $person = Person::factory()->create();

    syncEntities()($article, [
        ['type' => 'company', 'id' => $company->id, 'role' => EntityRole::Primary, 'prominence' => 90],
        ['type' => 'person', 'id' => $person->id],
    ]);

    expect($article->mentions()->count())->toBe(2)
        ->and($company->fresh()->mentions_count)->toBe(1)
        ->and($person->fresh()->mentions_count)->toBe(1);
});

it('decrements the counter of an entity that is removed', function (): void {
    $article = Article::factory()->create();
    $kept = Company::factory()->create();
    $dropped = Company::factory()->create();

    syncEntities()($article, [
        ['type' => 'company', 'id' => $kept->id],
        ['type' => 'company', 'id' => $dropped->id],
    ]);

    expect($dropped->fresh()->mentions_count)->toBe(1);

    syncEntities()($article, [['type' => 'company', 'id' => $kept->id]]);

    expect($kept->fresh()->mentions_count)->toBe(1)
        ->and($dropped->fresh()->mentions_count)->toBe(0)
        ->and($article->mentions()->count())->toBe(1);
});

it('keeps counters correct when the role changes but the entity does not', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    syncEntities()($article, [['type' => 'company', 'id' => $company->id, 'role' => EntityRole::Mentioned]]);
    syncEntities()($article, [['type' => 'company', 'id' => $company->id, 'role' => EntityRole::Primary, 'prominence' => 95]]);

    $mention = $article->mentions()->first();

    expect($company->fresh()->mentions_count)->toBe(1)
        ->and($mention->role)->toBe(EntityRole::Primary)
        ->and($mention->prominence)->toBe(95);
});

it('counts mentions of the same company across several articles', function (): void {
    $company = Company::factory()->create();

    foreach (range(1, 3) as $ignored) {
        syncEntities()(Article::factory()->create(), [['type' => 'company', 'id' => $company->id]]);
    }

    expect($company->fresh()->mentions_count)->toBe(3);
});

it('clears every mention when given an empty list', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    syncEntities()($article, [['type' => 'company', 'id' => $company->id]]);
    syncEntities()($article, []);

    expect($article->mentions()->count())->toBe(0)
        ->and($company->fresh()->mentions_count)->toBe(0);
});

it('collapses a duplicate entity instead of failing on the unique index', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    syncEntities()($article, [
        ['type' => 'company', 'id' => $company->id, 'role' => EntityRole::Primary],
        ['type' => 'company', 'id' => $company->id, 'role' => EntityRole::Mentioned],
    ]);

    expect($article->mentions()->count())->toBe(1)
        ->and($company->fresh()->mentions_count)->toBe(1);
});

it('clamps prominence into the 0-100 range', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();
    $person = Person::factory()->create();

    syncEntities()($article, [
        ['type' => 'company', 'id' => $company->id, 'prominence' => 500],
        ['type' => 'person', 'id' => $person->id, 'prominence' => -20],
    ]);

    expect($article->mentions()->where('entity_type', 'company')->value('prominence'))->toBe(100)
        ->and($article->mentions()->where('entity_type', 'person')->value('prominence'))->toBe(0);
});

it('rejects an entity type that is not in the morph map', function (): void {
    $article = Article::factory()->create();

    expect(fn () => syncEntities()($article, [['type' => 'unicorn', 'id' => 1]]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a mention with no id', function (): void {
    $article = Article::factory()->create();

    expect(fn () => syncEntities()($article, [['type' => 'company']]))
        ->toThrow(InvalidArgumentException::class);
});

it('counts only published articles towards a topic counter', function (): void {
    $topic = Topic::factory()->create();

    $published = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);
    $draft = Article::factory()->draft()->create();

    app(SyncArticleTopics::class)($published, [$topic->id]);
    app(SyncArticleTopics::class)($draft, [$topic->id]);

    expect($topic->fresh()->articles_count)->toBe(1);
});

it('excludes a future-dated article from the topic counter', function (): void {
    $topic = Topic::factory()->create();

    $future = Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->addWeek(),
    ]);

    app(SyncArticleTopics::class)($future, [$topic->id]);

    expect($topic->fresh()->articles_count)->toBe(0);
});

it('lowers a topic counter when an article is untagged', function (): void {
    $topic = Topic::factory()->create();
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
    ]);

    app(SyncArticleTopics::class)($article, [$topic->id]);
    expect($topic->fresh()->articles_count)->toBe(1);

    app(SyncArticleTopics::class)($article, []);
    expect($topic->fresh()->articles_count)->toBe(0);
});

it('realigns mention timestamps to publication time', function (): void {
    // Tagged well before the story runs, which is the normal editing order.
    $this->travelTo(now()->subWeeks(2));

    $chief = staff('editor_in_chief');
    $article = publishableArticle(['status' => ArticleStatus::Ready]);
    $company = Company::factory()->create();

    syncEntities()($article, [['type' => 'company', 'id' => $company->id]]);

    $taggedAt = $article->mentions()->value('created_at');

    $this->travelBack();

    app(PublishArticle::class)($article->fresh(), $chief);

    $article->refresh();

    expect($article->mentions()->value('created_at')->timestamp)
        ->toBe($article->published_at->timestamp)
        ->and($article->mentions()->value('created_at')->timestamp)
        ->toBeGreaterThan($taggedAt->timestamp);
});

it('orders entity content by publication time not tagging time', function (): void {
    $chief = staff('editor_in_chief');
    $company = Company::factory()->create();

    // Tagged first, published second.
    $taggedEarly = publishableArticle(['status' => ArticleStatus::Ready]);
    syncEntities()($taggedEarly, [['type' => 'company', 'id' => $company->id]]);

    $taggedLate = publishableArticle(['status' => ArticleStatus::Ready]);
    syncEntities()($taggedLate, [['type' => 'company', 'id' => $company->id]]);

    // Publish in the opposite order to the tagging order. Both publication
    // times must land in the past, or the published() scope hides them.
    $this->travelTo(now()->subHour());
    app(PublishArticle::class)($taggedLate->fresh(), $chief);
    $this->travelBack();

    app(PublishArticle::class)($taggedEarly->fresh(), $chief);

    $order = EntityContentQuery::for($company)->mentions()->pluck('mentionable_id')->all();

    // Newest publication first, regardless of which was tagged first.
    expect($order)->toBe([$taggedEarly->id, $taggedLate->id]);
});
