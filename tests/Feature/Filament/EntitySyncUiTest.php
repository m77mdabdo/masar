<?php

declare(strict_types=1);

use App\Enums\EntityRole;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Company;
use App\Models\Person;
use App\Models\Topic;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(staff('editor_in_chief'));
});

it('writes entity selections through SyncArticleEntities', function (): void {
    $article = publishableArticle();
    $company = Company::factory()->create();
    $person = Person::factory()->create();

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->fillForm([
            'entities' => [
                'company' => [['id' => $company->id, 'role' => EntityRole::Primary->value, 'prominence' => 90]],
                'person' => [['id' => $person->id, 'role' => EntityRole::Mentioned->value, 'prominence' => 20]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $article->refresh();

    expect($article->mentions()->count())->toBe(2)
        ->and($company->fresh()->mentions_count)->toBe(1)
        ->and($person->fresh()->mentions_count)->toBe(1);

    $mention = $article->mentions()->where('entity_type', 'company')->first();

    expect($mention->role)->toBe(EntityRole::Primary)
        ->and($mention->prominence)->toBe(90);
});

it('keeps counters correct when an entity is removed', function (): void {
    $article = publishableArticle();
    $kept = Company::factory()->create();
    $dropped = Company::factory()->create();

    $page = livewire(EditArticle::class, ['record' => $article->getKey()])
        ->fillForm(['entities' => ['company' => [
            ['id' => $kept->id, 'role' => EntityRole::Primary->value, 'prominence' => 80],
            ['id' => $dropped->id, 'role' => EntityRole::Mentioned->value, 'prominence' => 30],
        ]]])
        ->call('save');

    expect($dropped->fresh()->mentions_count)->toBe(1);

    $page->fillForm(['entities' => ['company' => [
        ['id' => $kept->id, 'role' => EntityRole::Primary->value, 'prominence' => 80],
    ]]])->call('save');

    expect($kept->fresh()->mentions_count)->toBe(1)
        ->and($dropped->fresh()->mentions_count)->toBe(0)
        ->and($article->fresh()->mentions()->count())->toBe(1);
});

it('loads existing mentions back into the form', function (): void {
    $article = publishableArticle();
    $company = Company::factory()->create();

    $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
        'role' => EntityRole::Secondary,
        'prominence' => 44,
    ]);

    $state = livewire(EditArticle::class, ['record' => $article->getKey()])->get('data');

    // Repeater state is keyed by uuid, not by index.
    $row = array_values($state['entities']['company'])[0];

    // Select state is stringly typed on the way into the form; the Action casts
    // it back on the way out.
    expect($row['id'])->toEqual($company->id)
        ->and($row['prominence'])->toEqual(44)
        ->and($row['role'])->toBe(EntityRole::Secondary->value);
});

it('syncs topics and keeps the published-only counter correct', function (): void {
    $topic = Topic::factory()->create();
    $article = publishableArticle();

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->fillForm(['topic_ids' => [$topic->id]])
        ->call('save');

    // Not published yet, so the counter stays at zero.
    expect($article->fresh()->topics()->count())->toBe(1)
        ->and($topic->fresh()->articles_count)->toBe(0);
});

it('flattens repeater state into the shape the action expects', function (): void {
    $rows = EditArticle::flattenEntities([
        'company' => [['id' => 3, 'role' => 'primary', 'prominence' => 80], ['id' => null]],
        'person' => [['id' => 7]],
    ]);

    // Blank rows an editor left behind are dropped, not sent as id 0.
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['type' => 'company', 'id' => 3, 'role' => 'primary', 'prominence' => 80])
        ->and($rows[1]['type'])->toBe('person');
});
