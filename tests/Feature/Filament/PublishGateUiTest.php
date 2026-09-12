<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Support\ArticleTransitionActions;
use App\Filament\Resources\Articles\Support\GateTabMap;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(staff('editor_in_chief'));
});

it('disables publish while the gate has anything to say', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);

    $publish = collect(ArticleTransitionActions::for($article))
        ->firstWhere(fn ($a): bool => $a->getName() === 'publish');

    expect($publish)->not->toBeNull()
        ->and($publish->isDisabled())->toBeTrue();
});

it('puts the reasons in the publish tooltip', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);

    $publish = collect(ArticleTransitionActions::for($article))
        ->firstWhere(fn ($a): bool => $a->getName() === 'publish');

    // An editor must never be told "no" without being told why.
    expect($publish->getTooltip())->toContain('ملخص');
});

it('enables publish once the gate passes', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    $publish = collect(ArticleTransitionActions::for($article))
        ->firstWhere(fn ($a): bool => $a->getName() === 'publish');

    expect($publish->isDisabled())->toBeFalse()
        ->and($publish->getTooltip())->toBeNull();
});

it('renders the gate checklist on the edit page', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);

    $failures = livewire(EditArticle::class, ['record' => $article->getKey()])
        ->instance()->gateFailures();

    expect($failures)->toHaveCount(1)
        ->and($failures[0]['message'])->toContain('ملخص')
        ->and($failures[0]['tab'])->toBe('story');
});

it('shows an empty checklist when the article is publishable', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    expect(livewire(EditArticle::class, ['record' => $article->getKey()])
        ->instance()->gateFailures())->toBe([]);
});

it('points every gate rule at a tab except fact check', function (): void {
    $rules = ['summary', 'source', 'hero_image', 'hero_alt', 'why_it_matters', 'sponsor_name', 'fact_check'];

    $decorated = GateTabMap::decorate(
        array_map(fn (string $r): array => ['rule' => $r, 'message' => 'x'], $rules),
    );

    foreach ($decorated as $index => $row) {
        if ($rules[$index] === 'fact_check') {
            // A workflow stage, not a field: there is nothing on the page to jump to.
            expect($row['tab_label'])->toBe('');

            continue;
        }

        expect($row['tab'])->not->toBeEmpty()
            ->and($row['tab_label'])->not->toBeEmpty();
    }
});

it('actually publishes through the action when the gate passes', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready]);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->callAction(TestAction::make('publish'));

    expect($article->fresh()->status)->toBe(ArticleStatus::Published)
        ->and($article->fresh()->published_at)->not->toBeNull();
});

it('refuses to publish from the page when the gate fails', function (): void {
    $article = publishableArticle(['status' => ArticleStatus::Ready, 'why_it_matters' => null]);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->callAction(TestAction::make('publish'));

    expect($article->fresh()->status)->toBe(ArticleStatus::Ready);
});
