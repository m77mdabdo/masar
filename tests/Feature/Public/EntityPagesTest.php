<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\EntityRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Person;
use App\Models\Topic;
use App\Queries\EntityProfileQuery;
use Illuminate\Support\Facades\DB;

/**
 * Person, topic and author pages.
 *
 * The same question the company page answers, asked where it earns its place.
 * A person is worth knowing what they are quoted *on* and who they appear
 * beside; a topic is worth knowing who turns up in it. An author page is an
 * E-E-A-T surface, so its structure matters more than its visual depth.
 */
function mentioningArticle(array $entities, array $attributes = []): App\Models\Article
{
    $article = publishableArticle(array_merge([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'locale' => 'ar',
    ], $attributes));

    foreach ($entities as [$type, $id]) {
        $article->mentions()->create([
            'entity_type' => $type,
            'entity_id' => $id,
            'role' => EntityRole::Primary,
            'prominence' => 80,
        ]);
    }

    return $article;
}

it('shows what a person is quoted on, by section', function (): void {
    $category = Category::factory()->create(['slug' => 'saudi']);
    $person = Person::factory()->create();

    mentioningArticle([['person', $person->getKey()]], ['category_id' => $category->getKey()]);
    mentioningArticle([['person', $person->getKey()]], ['category_id' => $category->getKey()]);

    $sections = EntityProfileQuery::for($person)->sections();

    expect($sections)->toHaveCount(1)
        ->and($sections->first()['count'])->toBe(2)
        ->and($sections->first()['slug'])->toBe('saudi');
});

it('shows the companies a person appears beside', function (): void {
    $person = Person::factory()->create();
    $company = Company::factory()->create();

    mentioningArticle([['person', $person->getKey()], ['company', $company->getKey()]]);
    mentioningArticle([['person', $person->getKey()], ['company', $company->getKey()]]);

    $html = $this->get("/ar/people/{$person->slug}")->assertOk()->getContent();

    expect($html)->toContain('يظهر مع')
        ->and($html)->toContain($company->name);
});

it('renders no graph sections on a person we have never quoted', function (): void {
    $person = Person::factory()->create();

    $html = $this->get("/ar/people/{$person->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('يتحدث في')
        ->and($html)->not->toContain('يظهر مع');
});

it('shows who appears most in a topic', function (): void {
    // A topic links through article_topic, not entity_mentions, so the question
    // runs the other way: given these articles, who is in them.
    $topic = Topic::factory()->create();
    $company = Company::factory()->create();

    foreach (range(1, 3) as $i) {
        $article = mentioningArticle([['company', $company->getKey()]]);
        $article->topics()->attach($topic->getKey());
    }

    $articleIds = DB::table('article_topic')->where('topic_id', $topic->getKey())->select('article_id');
    $entities = EntityProfileQuery::entitiesInArticles($articleIds);

    expect($entities->get('company')->first()['count'])->toBe(3)
        ->and($entities->get('company')->first()['slug'])->toBe($company->slug);
});

it('hydrates topic entities without an N+1', function (): void {
    // The block most likely to grow an N+1 later, because it is the one someone
    // will "improve" by adding a relation.
    //
    // Asserted as *boundedness*, not as a magic number: the same work over
    // three times the data must cost the same number of queries. A fixed
    // threshold only says "not more than n today" and drifts into being
    // adjusted upward until it means nothing.
    $count = function (int $companies): int {
        $topic = Topic::factory()->create();

        foreach (Company::factory()->count($companies)->create() as $company) {
            $article = mentioningArticle([['company', $company->getKey()]]);
            $article->topics()->attach($topic->getKey());
        }

        $ids = DB::table('article_topic')->where('topic_id', $topic->getKey())->select('article_id');

        DB::flushQueryLog();
        DB::enableQueryLog();
        EntityProfileQuery::entitiesInArticles($ids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(4))->toBe($count(12));
});

it('publishes the author page as a ProfilePage wrapping a Person', function (): void {
    // The shape Google documents for an author page — it is what lets articles
    // be attributed to a person rather than to a URL.
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDay(),
        'locale' => 'ar',
    ]);

    $html = $this->get("/ar/authors/{$article->author_id}")->assertOk()->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $types = array_map(fn (string $j): string => (string) (json_decode($j, true)['@type'] ?? ''), $m[1]);

    expect($types)->toContain('ProfilePage')
        ->and($types)->toContain('BreadcrumbList');

    $profile = collect($m[1])
        ->map(fn (string $j): array => (array) json_decode($j, true))
        ->firstWhere('@type', 'ProfilePage');

    expect($profile['mainEntity']['@type'])->toBe('Person')
        ->and($profile['mainEntity'])->toHaveKey('mainEntityOfPage')
        // No invented biography and no unverified profile links: a fabricated
        // credential is the fabrication rule pointed at the author.
        ->and($profile['mainEntity'])->not->toHaveKey('description')
        ->and($profile['mainEntity'])->not->toHaveKey('sameAs');
});
