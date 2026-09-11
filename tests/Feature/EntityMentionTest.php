<?php

declare(strict_types=1);

use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Person;
use Illuminate\Database\UniqueConstraintViolationException;

it('aggregates content across multiple content types for one company', function (): void {
    $company = Company::factory()->create();

    $article = Article::factory()->create();
    $opportunity = Opportunity::factory()->create();

    $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
        'role' => EntityRole::Primary,
        'prominence' => 90,
    ]);

    $opportunity->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
        'role' => EntityRole::Secondary,
        'prominence' => 40,
    ]);

    $mentions = $company->mentions()->with('mentionable')->get();

    expect($mentions)->toHaveCount(2)
        ->and($mentions->pluck('mentionable')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$article->id, $opportunity->id])->sort()->values()->all())
        ->and($mentions->pluck('mentionable_type')->unique()->sort()->values()->all())
        ->toBe(['article', 'opportunity']);
});

it('resolves the entity side of a mention polymorphically', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();
    $person = Person::factory()->create();

    $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]);

    $article->mentions()->create([
        'entity_type' => $person->getMorphClass(),
        'entity_id' => $person->getKey(),
    ]);

    $entities = $article->mentions()->with('entity')->get()->pluck('entity');

    expect($entities)->toHaveCount(2)
        ->and($entities->whereInstanceOf(Company::class))->toHaveCount(1)
        ->and($entities->whereInstanceOf(Person::class))->toHaveCount(1);
});

it('stores short morph aliases rather than class names', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    $mention = $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]);

    expect($mention->mentionable_type)->toBe('article')
        ->and($mention->entity_type)->toBe('company');
});

it('refuses to record the same entity on the same content twice', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]);

    expect(fn () => $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same entity to be mentioned by different content', function (): void {
    $company = Company::factory()->create();
    $first = Article::factory()->create();
    $second = Article::factory()->create();

    foreach ([$first, $second] as $article) {
        $article->mentions()->create([
            'entity_type' => $company->getMorphClass(),
            'entity_id' => $company->getKey(),
        ]);
    }

    expect($company->mentions()->count())->toBe(2);
});

it('casts role to the EntityRole enum and defaults to mentioned', function (): void {
    $article = Article::factory()->create();
    $company = Company::factory()->create();

    $mention = $article->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
    ]);

    expect($mention->fresh()->role)->toBe(EntityRole::Mentioned);
});

it('filters a company mentions by role', function (): void {
    $company = Company::factory()->create();

    Article::factory()->create()->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
        'role' => EntityRole::Primary,
    ]);

    Article::factory()->create()->mentions()->create([
        'entity_type' => $company->getMorphClass(),
        'entity_id' => $company->getKey(),
        'role' => EntityRole::Mentioned,
    ]);

    expect($company->mentions()->ofRole(EntityRole::Primary)->count())->toBe(1);
});
