<?php

declare(strict_types=1);

use App\Actions\Articles\EvaluatePublishGate;
use App\Actions\Translation\CreateTranslationDraft;
use App\Actions\Translation\MarkTranslationReviewed;
use App\Actions\Translation\RetryTranslation;
use App\Enums\TranslationStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Jobs\TranslateArticle;
use App\Models\Article;
use App\Services\Translation\SegmentGuard;
use App\Services\Translation\TranslationProvider;
use App\Services\Translation\TranslationRejected;
use App\Services\Translation\TranslationResult;
use App\Support\TranslatableContent;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/**
 * A provider for tests: answers with a fixed transform of each segment, so a
 * test can see that the job really sent the source's segments and wrote back
 * what came out. Markers ⟦n⟧ are kept unless told to drop them.
 */
function fakeProvider(callable $each): TranslationProvider
{
    return new class($each) implements TranslationProvider
    {
        public array $received = [];

        public function __construct(private $each) {}

        public function name(): string
        {
            return 'fake';
        }

        public function translate(array $segments, string $from, string $to, array $glossary): ?TranslationResult
        {
            $this->received = $segments;

            return new TranslationResult(array_map($this->each, $segments), 42);
        }
    };
}

function sourceArticle(): Article
{
    $article = publishableArticle();
    $article->blocks()->create(['type' => 'paragraph', 'data' => ['text' => 'نمت الإيرادات 30% إلى 3,196 مليون ريال وفق Aramco.'], 'sort_order' => 1]);

    return $article->fresh();
}

it('opens a draft in the same translation group without touching the source', function (): void {
    Queue::fake();
    $source = sourceArticle();
    $before = $source->only(['title', 'locale', 'status', 'translation_group_id']);

    $draft = app(CreateTranslationDraft::class)($source, 'en', staff('editor'));

    expect($draft->translation_group_id)->toBe($source->translation_group_id)
        ->and($draft->locale)->toBe('en')
        ->and($draft->source_article_id)->toBe($source->id)
        ->and($draft->published_at)->toBeNull()
        ->and($draft->translation_status)->toBe(TranslationStatus::Queued)
        ->and($draft->blocks)->toHaveCount($source->blocks->count())
        ->and($draft->sources)->toHaveCount($source->sources->count())
        ->and($draft->slug)->toBe($source->slug)
        ->and($source->fresh()->only(['title', 'locale', 'status', 'translation_group_id']))->toBe($before);

    // Scalars only on the queue (CLAUDE.md §3).
    Queue::assertPushed(TranslateArticle::class, fn (TranslateArticle $job): bool => $job->draftId === $draft->id);
});

it('leaves the draft for a person when the provider declines, as the shipped one always does', function (): void {
    $source = sourceArticle();

    $draft = app(CreateTranslationDraft::class)($source, 'fr', staff('editor'))->fresh();

    expect($draft->translation_status)->toBe(TranslationStatus::AwaitingTranslator)
        ->and($draft->translation_origin)->toBeNull()
        ->and($draft->title)->toBe($source->title);
});

it('refuses a duplicate, the source language, and an unknown language', function (string $locale): void {
    Queue::fake();
    $source = sourceArticle();
    app(CreateTranslationDraft::class)($source, 'en', staff('editor'));

    expect(fn () => app(CreateTranslationDraft::class)($source, $locale, staff('editor')))
        ->toThrow(TranslationNotAllowed::class);
})->with(['en', 'ar', 'xx']);

it('refuses a user without the translate permission', function (): void {
    expect(fn () => app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('fact_checker')))
        ->toThrow(AuthorizationException::class);
});

it('keeps an unreviewed translation out of publication, and lets a reviewed one through', function (): void {
    Queue::fake();
    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('writer'));
    $gate = fn (): array => array_column(app(EvaluatePublishGate::class)->detailed($draft->fresh()), 'rule');

    expect($gate())->toContain('translation_review');

    app(MarkTranslationReviewed::class)($draft->fresh(), staff('editor'));

    expect($gate())->not->toContain('translation_review')
        ->and($draft->fresh()->translation_status)->toBe(TranslationStatus::Reviewed);
});

it('does not let a translator sign off their own translation', function (): void {
    Queue::fake();
    $editor = staff('editor');
    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', $editor);

    expect(fn () => app(MarkTranslationReviewed::class)($draft->fresh(), $editor))
        ->toThrow(AuthorizationException::class);
});

it('sends segments, not structure, keeps figures and names out of the provider’s reach, and writes the answer back', function (): void {
    config()->set('masar.translation.allow_external', true);
    $provider = fakeProvider(fn (string $s): string => 'Revenue rose '.implode(' ', array_map(fn ($m) => $m, preg_match_all('/⟦\d+⟧/u', $s, $m) ? $m[0] : [])));
    app()->instance(TranslationProvider::class, $provider);

    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('editor'))->fresh();
    $sent = $provider->received['blocks.0.text'];

    // What the provider saw: no figure, no Latin name, only markers.
    expect($sent)->not->toContain('3,196')->not->toContain('30%')->not->toContain('Aramco')->toContain('⟦0⟧')
        ->and($draft->translation_status)->toBe(TranslationStatus::NeedsReview)
        ->and($draft->translation_origin)->toBe('fake')
        // What came back: the protected originals restored verbatim.
        ->and($draft->blocks->first()->data['text'])->toContain('3,196')->toContain('30%')->toContain('Aramco');
});

it('refuses text from any provider while external sending is off', function (): void {
    config()->set('masar.translation.allow_external', false);
    $provider = fakeProvider(fn (string $s): string => 'translated');
    app()->instance(TranslationProvider::class, $provider);

    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('editor'))->fresh();

    expect($provider->received)->toBe([])
        ->and($draft->translation_status)->toBe(TranslationStatus::AwaitingTranslator);
});

it('rejects a result that lost a protected token, and records why without quoting content', function (): void {
    config()->set('masar.translation.allow_external', true);
    app()->instance(TranslationProvider::class, fakeProvider(fn (string $s): string => 'Revenue rose'));
    Queue::fake();
    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('editor'));

    $job = new TranslateArticle($draft->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(TranslationRejected::class);

    $job->failed(new TranslationRejected('Segment blocks.0.text lost or repeated protected token 0.'));
    $draft->refresh();

    expect($draft->translation_status)->toBe(TranslationStatus::Failed)
        ->and($draft->translation_error)->not->toContain('3,196')
        ->and($draft->title)->toBe($draft->source->title);
});

it('never overwrites a reviewed translation, and will not re-run one', function (): void {
    Queue::fake();
    $draft = app(CreateTranslationDraft::class)(sourceArticle(), 'en', staff('writer'));
    $draft->forceFill(['title' => 'A headline a person wrote'])->save();
    app(MarkTranslationReviewed::class)($draft->fresh(), staff('editor'));

    config()->set('masar.translation.allow_external', true);
    app()->instance(TranslationProvider::class, fakeProvider(fn (string $s): string => 'machine'));
    app()->call([new TranslateArticle($draft->id), 'handle']);

    expect($draft->fresh()->title)->toBe('A headline a person wrote')
        ->and(fn () => app(RetryTranslation::class)($draft->fresh(), staff('editor')))->toThrow(TranslationNotAllowed::class);
});

it('marks a translation outdated when a sentence in its source changes, and clears it on review', function (): void {
    Queue::fake();
    $source = sourceArticle();
    $draft = app(CreateTranslationDraft::class)($source, 'en', staff('writer'));

    expect($draft->fresh()->translationIsOutdated())->toBeFalse();

    // Structure is not language: re-ordering does not make it stale.
    $source->blocks()->update(['sort_order' => 9]);
    expect($draft->fresh()->translationIsOutdated())->toBeFalse();

    $block = $source->blocks()->first();
    $block->update(['data' => ['text' => 'جملة جديدة']]);
    expect($draft->fresh()->translationIsOutdated())->toBeTrue();

    app(MarkTranslationReviewed::class)($draft->fresh(), staff('editor'));
    expect($draft->fresh()->translationIsOutdated())->toBeFalse();
});

it('only accepts text written in the target edition’s script', function (string $text, string $locale, bool $ok): void {
    expect(app(SegmentGuard::class)->writtenIn($text, $locale))->toBe($ok);
})->with([
    ['Revenue rose sharply', 'en', true],
    ['نمت الإيرادات', 'en', false],
    ['收入大幅增长', 'zh-hans', true],
    ['राजस्व में तेज़ वृद्धि हुई', 'hi', true],
    // Arabic returned for the Urdu edition: right block of Unicode, wrong language.
    ['نمت الإيرادات بشكل كبير', 'ur-pk', false],
    ['آمدنی میں نمایاں اضافہ ہوا ہے', 'ur-pk', true],
]);

it('reaches every translation rule through the buttons an editor actually presses', function (): void {
    // The policy methods are only protection if something calls them
    // (CLAUDE.md §7). This is the path a user takes.
    Queue::fake();
    $writer = staff('writer');
    $source = sourceArticle();
    // Writers see their own articles in the panel; this one is theirs.
    $source->forceFill(['author_id' => $writer->id])->save();
    $this->actingAs($writer);

    livewire(EditArticle::class, ['record' => $source->getKey()])
        ->callAction(TestAction::make('translate'), ['locale' => 'en'])
        ->assertHasNoActionErrors();

    $draft = Article::query()->where('source_article_id', $source->id)->sole();

    // The translator sees no sign-off button on their own translation…
    livewire(EditArticle::class, ['record' => $draft->getKey()])
        ->assertActionHidden(TestAction::make('reviewTranslation'));

    // …an editor does, and pressing it signs it off.
    $this->actingAs(staff('editor'));
    livewire(EditArticle::class, ['record' => $draft->getKey()])
        ->assertActionVisible(TestAction::make('reviewTranslation'))
        ->callAction(TestAction::make('reviewTranslation'));

    expect($draft->fresh()->translation_status)->toBe(TranslationStatus::Reviewed);
});

it('keeps the editorial-standards promise of human-written text true', function (): void {
    // pages.standards.ai_body tells readers every story is written by a
    // person. That is true only while no provider produces text. A real
    // driver added to AppServiceProvider fails here, on purpose: the page has
    // to say what then happens (machine draft, human review) before it ships.
    foreach (['null', 'deepl', 'anything'] as $driver) {
        config()->set('masar.translation.driver', $driver);
        expect(app(TranslationProvider::class))->toBeInstanceOf(\App\Services\Translation\NullTranslationProvider::class);
    }

    // Since 2026-10-29 the page says what happens: originals are written by a
    // person; the other editions carry machine-assisted translations,
    // published on the owner's authorisation and not yet natively reviewed.
    expect(__('pages.standards.ai_body', [], 'en'))->toContain('machine-assisted')
        ->toContain('not yet reviewed by native speakers');
});
