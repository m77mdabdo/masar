<?php

declare(strict_types=1);

use App\Enums\OpportunityPotential;
use App\Models\Article;
use App\Models\Company;
use App\Models\Opportunity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * These tests lock in the reason the enum columns were converted to strings:
 * adding a workflow status must never require an ALTER that rewrites the table.
 *
 * If someone reintroduces a native ENUM column, the first test here fails.
 */
it('has no native enum columns anywhere in the schema', function (): void {
    $enums = DB::select(
        'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND DATA_TYPE = ?',
        [DB::getDatabaseName(), 'enum'],
    );

    $names = array_map(
        static fn (object $c): string => "{$c->TABLE_NAME}.{$c->COLUMN_NAME}",
        $enums,
    );

    expect($names)->toBe([]);
});

it('stores a workflow status the PHP enum does not yet know about', function (): void {
    // The point of the conversion: a new status is a code change, not a migration.
    $article = Article::factory()->create();

    DB::table('articles')->where('id', $article->id)->update(['status' => 'legal_review']);

    expect(DB::table('articles')->where('id', $article->id)->value('status'))
        ->toBe('legal_review');
});

it('keeps the status column wide enough for a realistic new status', function (): void {
    $article = Article::factory()->create();
    $status = str_repeat('a', 32);

    DB::table('articles')->where('id', $article->id)->update(['status' => $status]);

    expect(DB::table('articles')->where('id', $article->id)->value('status'))->toBe($status);
});

it('casts opportunity potential to the OpportunityPotential enum', function (): void {
    $opportunity = Opportunity::factory()->create(['potential' => OpportunityPotential::High]);

    expect($opportunity->fresh()->potential)->toBe(OpportunityPotential::High)
        ->and(DB::table('opportunities')->where('id', $opportunity->id)->value('potential'))
        ->toBe('high');
});

it('orders potentials by weight rather than alphabetically', function (): void {
    // Stored as strings, so SQL ordering would give high, low, medium.
    $sorted = collect(OpportunityPotential::cases())
        ->sortByDesc(fn (OpportunityPotential $p): int => $p->weight())
        ->values()
        ->all();

    expect($sorted)->toBe([
        OpportunityPotential::High,
        OpportunityPotential::Medium,
        OpportunityPotential::Low,
    ]);
});

it('gives every potential an Arabic label', function (): void {
    foreach (OpportunityPotential::cases() as $potential) {
        expect(preg_match('/\p{Arabic}/u', $potential->label()))->toBe(1);
    }
});

it('no longer carries the redundant mentionable index on entity_mentions', function (): void {
    $indexes = collect(DB::select('SHOW INDEX FROM entity_mentions'))
        ->pluck('Key_name')
        ->unique()
        ->values()
        ->all();

    expect($indexes)->not->toContain('entity_mentions_mentionable_idx')
        // The unique index still covers those columns as a left prefix.
        ->and($indexes)->toContain('entity_mentions_unique')
        ->and($indexes)->toContain('entity_mentions_entity_idx');
});

it('still enforces mention uniqueness after dropping the prefix index', function (): void {
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
