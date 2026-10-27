<?php

declare(strict_types=1);

use App\Actions\Articles\SyncArticleEntities;
use App\Enums\ArticleStatus;
use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Company;
use Illuminate\Support\Facades\Schema;

/**
 * Two numbers the sweep found wrong, and the shape of each fix.
 *
 * `mentions_count` counted drafts as coverage, and four separate places
 * implemented "published" differently — two actions and two queries in
 * `masar:recount`, which disagreed with each other and silently rebuilt the
 * wrong values over the right ones.
 *
 * `reading_time` was frozen at publish and then won over the accessor, so it
 * was wrong for every article edited afterwards.
 */
it('counts only published mentions', function (): void {
    $company = Company::factory()->create();

    $published = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()->subHour()]);
    $draft = publishableArticle(['status' => ArticleStatus::Writing, 'published_at' => null]);

    foreach ([$published, $draft] as $article) {
        $article->mentions()->create([
            'entity_type' => 'company',
            'entity_id' => $company->getKey(),
            'role' => EntityRole::Primary,
            'prominence' => 80,
        ]);
    }

    app(SyncArticleEntities::class)->recountAll();

    // The draft is not coverage. It was counted as coverage, and
    // ComposeHomepage filters the entities section on this column — so an
    // entity we had only ever named in a draft appeared as one we cover.
    expect((int) $company->fresh()->mentions_count)->toBe(1);
});

it('does not count a mention on an article published in the future', function (): void {
    $company = Company::factory()->create();

    publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()->addWeek()])
        ->mentions()->create([
            'entity_type' => 'company',
            'entity_id' => $company->getKey(),
            'role' => EntityRole::Primary,
            'prominence' => 80,
        ]);

    app(SyncArticleEntities::class)->recountAll();

    expect((int) $company->fresh()->mentions_count)->toBe(0);
});

it('reports drift against the same rule it writes', function (): void {
    // The drift query measured an unfiltered count, so it agreed with the bug
    // and reported zero rows out of date on data where every row was wrong.
    $company = Company::factory()->create();

    publishableArticle(['status' => ArticleStatus::Writing, 'published_at' => null])
        ->mentions()->create([
            'entity_type' => 'company',
            'entity_id' => $company->getKey(),
            'role' => EntityRole::Primary,
            'prominence' => 80,
        ]);

    // A wrong value on the row, as the old counter would have left it.
    $company->forceFill(['mentions_count' => 1])->save();

    $this->artisan('masar:recount', ['--dry-run' => true])
        ->expectsOutputToContain('1 row(s) out of date')
        ->assertSuccessful();

    // And the rebuild corrects it rather than restoring it.
    $this->artisan('masar:recount')->assertSuccessful();

    expect((int) $company->fresh()->mentions_count)->toBe(0);
});

it('has no stored reading_time column left to drift', function (): void {
    expect(Schema::hasColumn('articles', 'reading_time'))->toBeFalse();
});

it('recomputes reading time after the body changes', function (): void {
    // The failure the column caused: frozen at publish, then wrong for every
    // article edited afterwards, on all ten surfaces that show it.
    $article = publishableArticle([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'body' => str_repeat('كلمة ', 180),
    ]);

    $before = $article->fresh()->reading_time;

    $article->forceFill(['body' => str_repeat('كلمة ', 1800)])->save();

    expect($article->fresh()->reading_time)->toBeGreaterThan($before);
});

it('never reports a reading time below one minute', function (): void {
    $article = publishableArticle(['body' => 'كلمتان فقط']);

    expect($article->fresh()->reading_time)->toBe(1);
});
