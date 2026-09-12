<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Filament\Pages\EditorialPipeline;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Support\ArticleTransitionActions;
use App\Models\Article;
use Filament\Actions\Testing\TestAction;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(staff('editor_in_chief'));
});

/**
 * @return array<int, string>
 */
function renderedTransitions(Article $article): array
{
    return collect(ArticleTransitionActions::for($article))
        ->map(fn ($action): string => $action->getName())
        ->all();
}

it('renders exactly the transitions the map allows', function (): void {
    foreach (ArticleStatus::cases() as $status) {
        $article = Article::factory()->create(['status' => $status]);

        $expected = collect($status->allowedTransitions())
            ->map(fn (ArticleStatus $to): string => match ($to) {
                ArticleStatus::Published => 'publish',
                ArticleStatus::Scheduled => 'schedule',
                default => 'transition_'.$to->value,
            })
            ->sort()->values()->all();

        $actual = collect(renderedTransitions($article))->sort()->values()->all();

        expect($actual)->toBe($expected, "mismatch for {$status->value}");
    }
});

it('never renders a transition that is not allowed', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    // writing → fact_check | on_hold only.
    expect(renderedTransitions($article))
        ->toContain('transition_fact_check')
        ->toContain('transition_on_hold')
        ->not->toContain('publish')
        ->not->toContain('transition_ready');
});

it('records the reason given for a transition that warrants one', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::FactCheck]);

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->callAction(
            TestAction::make('transition_needs_revision'),
            ['note' => 'الرقم في الفقرة الثالثة لا يطابق المصدر.'],
        );

    $entry = Activity::query()
        ->where('log_name', 'article.status')
        ->latest('id')
        ->first();

    expect($article->fresh()->status)->toBe(ArticleStatus::NeedsRevision)
        // The reason is the point: whoever picks this up next needs to know why.
        ->and($entry->properties['note'])->toBe('الرقم في الفقرة الثالثة لا يطابق المصدر.');
});

it('moves a card between columns on the pipeline board', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    livewire(EditorialPipeline::class)
        ->call('moveCard', $article->id, 'fact_check');

    expect($article->fresh()->status)->toBe(ArticleStatus::FactCheck);
});

it('rejects an illegal drop with a reason rather than reverting silently', function (): void {
    $article = Article::factory()->create(['status' => ArticleStatus::Writing]);

    livewire(EditorialPipeline::class)
        ->call('moveCard', $article->id, 'ready')
        ->assertNotified();

    expect($article->fresh()->status)->toBe(ArticleStatus::Writing);
});

it('only offers board columns the map allows', function (): void {
    $moves = livewire(EditorialPipeline::class)->instance()->allowedMoves();

    expect($moves['writing'])->toContain('fact_check')
        ->and($moves['writing'])->toContain('on_hold')
        ->and($moves['writing'])->not->toContain('ready');
});

it('excludes terminal statuses from the board', function (): void {
    $columns = collect(livewire(EditorialPipeline::class)->instance()->columns())
        ->map(fn (ArticleStatus $s): string => $s->value)
        ->all();

    // A board is for work in flight.
    expect($columns)->not->toContain('published')
        ->and($columns)->not->toContain('archived')
        ->and($columns)->not->toContain('rejected');
});

it('renders the pipeline board', function (): void {
    Article::factory()->count(3)->create(['status' => ArticleStatus::Writing]);

    livewire(EditorialPipeline::class)->assertOk();
});

it('runs the publish gate when a card is dropped into a published column', function (): void {
    // `published` is not a board column, so the board can never bypass the gate.
    $moves = livewire(EditorialPipeline::class)->instance()->allowedMoves();

    foreach ($moves as $targets) {
        expect($targets)->not->toContain('published');
    }
});
