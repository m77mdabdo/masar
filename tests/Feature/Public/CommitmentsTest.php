<?php

declare(strict_types=1);

use App\Actions\Translation\ApproveTranslationFile;
use App\Actions\Translation\SyncTranslationReviews;
use App\Enums\ArticleStatus;
use App\Models\HomepageLayout;
use App\Models\TranslationReview;
use App\Support\EntityUrl;

/*
 * R12: a sentence on a public page that describes what the system does owes a
 * test (CLAUDE.md §5). These bind the sentences rewritten in the 2026-10-29
 * sweep to the mechanism each one names.
 */
beforeEach(fn () => HomepageLayout::factory()->create(['is_active' => true]));

it('cannot reach publication without writing, fact-checking and editorial review, in that order', function (): void {
    // pages.team.how_body: "the system does not allow any of these stages to
    // be skipped". Remove a stage from the transition map and publication
    // must become unreachable from a new article.
    $reachable = function (array $without): bool {
        $seen = [ArticleStatus::Idea];
        for ($i = 0; $i < count($seen); $i++) {
            foreach ($seen[$i]->allowedTransitions() as $next) {
                if (! in_array($next, $without, true) && ! in_array($next, $seen, true)) {
                    $seen[] = $next;
                }
            }
        }

        return in_array(ArticleStatus::Published, $seen, true);
    };

    expect($reachable([]))->toBeTrue();
    foreach ([ArticleStatus::Writing, ArticleStatus::FactCheck, ArticleStatus::EditorReview] as $stage) {
        expect($reachable([$stage]))->toBeFalse();
    }
});

it('says the interface is pending review until the register records a review, then says so', function (): void {
    app(SyncTranslationReviews::class)();

    $this->get('/en/editorial-standards')->assertOk()->assertSee(__('pages.standards.interface_pending', [], 'en'), false);

    TranslationReview::query()->where('locale', 'en')->each(fn ($r) => app(ApproveTranslationFile::class)($r, staff('editor_in_chief')));

    $this->get('/en/editorial-standards')->assertOk()
        ->assertSee(__('pages.standards.interface_reviewed', [], 'en'), false)
        ->assertDontSee(__('pages.standards.interface_pending', [], 'en'), false);
});

it('names on the advertise page the disclosure label the site actually renders', function (string $locale): void {
    $label = __('article.sponsored', [], $locale);

    $this->get("/{$locale}/advertise")->assertOk()->assertSee($label, false);
    expect(file_get_contents(resource_path('views/components/article/sponsored-flag.blade.php')))->toContain("__('article.sponsored')");
})->with(['ar', 'en', 'zh-hans']);

it('credits the hero photograph on the story page', function (): void {
    // site.footer.photo_credits: "each photograph is credited on the story page".
    $article = publishableArticle(['status' => ArticleStatus::Published, 'published_at' => now()->subHour()]);
    $article->heroMedia->setCustomProperty('credit', 'Pam Santos / Pexels')->save();

    $this->get(app(EntityUrl::class)->for($article->fresh(), 'ar'))->assertOk()->assertSee('Pam Santos / Pexels');
});

it('links to no legal page that does not exist', function (): void {
    $html = $this->get('/en')->getContent();

    expect($html)->not->toContain('>'.__('site.footer.privacy', [], 'en').'<')
        ->not->toContain('>'.__('site.footer.terms', [], 'en').'<')
        ->not->toContain('>'.__('site.footer.cookies', [], 'en').'<');
});

it('says the people in demo content are fictional only while the demo is on', function (): void {
    app(\App\Support\Settings::class)->set(\App\Support\DemoMode::SETTING, true);
    $this->get('/en/team')->assertSee(__('pages.team.experts_demo', [], 'en'), false);

    app(\App\Support\Settings::class)->set(\App\Support\DemoMode::SETTING, false);
    $this->get('/en/team')->assertDontSee(__('pages.team.experts_demo', [], 'en'), false);
});
