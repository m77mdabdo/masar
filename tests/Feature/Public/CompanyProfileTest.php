<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Company;
use App\Models\Person;
use App\Queries\EntityProfileQuery;
use Illuminate\Support\Facades\DB;

/**
 * The company profile is the payoff for the content graph: the page that
 * answers what a tag cannot. A list of articles about a company is something
 * any CMS produces. The shape of the coverage, how long it has run, and **who
 * else appears in the same stories** are what `entity_mentions` being
 * polymorphic actually buys.
 */
function articleMentioning(array $entities, array $attributes = []): App\Models\Article
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
            'role' => App\Enums\EntityRole::Primary,
            'prominence' => 80,
        ]);
    }

    return $article;
}

it('breaks coverage down by what kind of thing it is', function (): void {
    $company = Company::factory()->create();

    articleMentioning([['company', $company->getKey()]], ['content_type' => 'news']);
    articleMentioning([['company', $company->getKey()]], ['content_type' => 'news']);
    articleMentioning([['company', $company->getKey()]], ['content_type' => 'analysis']);

    $coverage = EntityProfileQuery::for($company)->coverage();

    expect($coverage->firstWhere('count', 2))->not->toBeNull()
        ->and($coverage->sum('count'))->toBe(3);
});

it('counts only published coverage', function (): void {
    $company = Company::factory()->create();

    articleMentioning([['company', $company->getKey()]]);
    $draft = articleMentioning([['company', $company->getKey()]]);
    $draft->forceFill(['status' => ArticleStatus::Writing, 'published_at' => null])->save();

    expect(EntityProfileQuery::for($company)->coverage()->sum('count'))->toBe(1);
});

it('groups the timeline by year', function (): void {
    $company = Company::factory()->create();

    articleMentioning([['company', $company->getKey()]], ['published_at' => now()->subYears(2)]);
    articleMentioning([['company', $company->getKey()]], ['published_at' => now()]);
    articleMentioning([['company', $company->getKey()]], ['published_at' => now()]);

    $timeline = EntityProfileQuery::for($company)->timeline();

    expect($timeline)->toHaveCount(2)
        // Oldest first: the page reads as a history, not a leaderboard.
        ->and($timeline->first()['year'])->toBeLessThan($timeline->last()['year'])
        ->and($timeline->last()['count'])->toBe(2);
});

it('finds who else appears in the same stories, and never the company itself', function (): void {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $person = Person::factory()->create();

    // Two stories name the company and the person; one also names the other company.
    articleMentioning([
        ['company', $company->getKey()],
        ['person', $person->getKey()],
    ]);
    articleMentioning([
        ['company', $company->getKey()],
        ['person', $person->getKey()],
        ['company', $other->getKey()],
    ]);

    $co = EntityProfileQuery::for($company)->coMentions();

    expect($co->get('person')->first()['count'])->toBe(2)
        ->and($co->get('company')->first()['count'])->toBe(1)
        // The subject of the page is not one of its own co-mentions.
        ->and($co->get('company')->pluck('slug'))->not->toContain($company->slug);
});

it('renders none of the graph sections when there is nothing to show', function (): void {
    // Nothing renders an empty section. A company we have never covered gets a
    // profile and an honest empty state, not four headings with nothing under
    // them.
    $company = Company::factory()->create();

    $html = $this->get("/ar/companies/{$company->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('شكل تغطيتنا')
        ->and($html)->not->toContain('يظهر معها')
        ->and($html)->toContain('لم نغطِّ هذه الشركة بعد');
});

it('hides the timeline when every article falls in one year', function (): void {
    // A one-bar chart states nothing. This is why the demo site shows no
    // timeline: all 72 seeded articles are dated in the same year.
    $company = Company::factory()->create();
    articleMentioning([['company', $company->getKey()]], ['published_at' => now()]);
    articleMentioning([['company', $company->getKey()]], ['published_at' => now()->subMonth()]);

    expect(EntityProfileQuery::for($company)->timeline())->toHaveCount(1)
        ->and($this->get("/ar/companies/{$company->slug}")->getContent())
        ->not->toContain('تغطيتنا عبر السنوات');
});

it('answers all three aggregates in a bounded number of queries', function (): void {
    // No query inside a loop (§5): one hydration pass per entity type, however
    // many entities come back.
    $company = Company::factory()->create();
    $people = Person::factory()->count(6)->create();

    foreach ($people as $person) {
        articleMentioning([['company', $company->getKey()], ['person', $person->getKey()]]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $profile = EntityProfileQuery::for($company);
    $profile->coverage();
    $profile->timeline();
    $profile->coMentions();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(8);
});
