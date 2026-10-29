<?php

declare(strict_types=1);

use App\Actions\Translation\ApproveTranslationFile;
use App\Actions\Translation\SyncTranslationReviews;
use App\Enums\TranslationReviewStatus;
use App\Exceptions\TranslationNotAllowed;
use App\Models\TranslationReview;
use Illuminate\Auth\Access\AuthorizationException;

/*
 * R11 (owner's ruling, 2026-10-29): an edition is public while its interface
 * translation is pending review. The register says which files a person has
 * read; it never says so on anyone's behalf.
 */
function frSite(): TranslationReview
{
    app(SyncTranslationReviews::class)();

    return TranslationReview::query()->where('path', 'lang/fr/site.php')->sole();
}

it('records every file of every edition as pending, and Arabic as the source', function (): void {
    app(SyncTranslationReviews::class)();

    $pending = TranslationReview::query()->where('locale', '!=', 'ar')->get();

    expect($pending)->not->toBeEmpty()
        ->and($pending->every(fn ($r) => $r->status === TranslationReviewStatus::PendingReview && $r->reviewer_id === null && $r->reviewed_at === null))->toBeTrue()
        ->and($pending->every(fn ($r) => $r->pending_strings === $r->total_strings && $r->total_strings > 0))->toBeTrue()
        ->and(TranslationReview::query()->where('locale', 'ar')->pluck('status')->unique()->all())->toBe([TranslationReviewStatus::Source]);
});

it('keeps every edition public while its review is pending', function (): void {
    app(SyncTranslationReviews::class)();

    foreach (['en', 'fr', 'hi', 'ur-pk', 'zh-hans'] as $code) {
        expect(TranslationReview::query()->where('locale', $code)->where('status', 'approved')->count())->toBe(0);
        $this->get('/'.$code)->assertOk();
    }
});

it('never records an approval by syncing, however often it runs', function (): void {
    app(SyncTranslationReviews::class)();
    app(SyncTranslationReviews::class)();

    expect(TranslationReview::query()->where('status', 'approved')->count())->toBe(0)
        ->and(TranslationReview::query()->whereNotNull('reviewer_id')->count())->toBe(0);
});

it('approves a file only for a named person with the permission', function (): void {
    $review = frSite();

    expect(fn () => app(ApproveTranslationFile::class)($review, staff('writer')))->toThrow(AuthorizationException::class);

    $chief = staff('editor_in_chief');
    app(ApproveTranslationFile::class)($review, $chief);

    expect($review->fresh())
        ->status->toBe(TranslationReviewStatus::Approved)
        ->reviewer_id->toBe($chief->id)
        ->reviewed_strings->toBe($review->total_strings)
        ->pending_strings->toBe(0);
});

it('refuses to approve the Arabic source as if it were a translation', function (): void {
    app(SyncTranslationReviews::class)();
    $source = TranslationReview::query()->where('path', 'lang/ar/site.php')->sole();

    expect(fn () => app(ApproveTranslationFile::class)($source, staff('editor_in_chief')))->toThrow(TranslationNotAllowed::class);
});

it('invalidates an approval when the file changes, and refuses to approve unseen words', function (): void {
    $review = frSite();
    app(ApproveTranslationFile::class)($review, staff('editor_in_chief'));

    // The words on disk change after the approval.
    $file = base_path('lang/fr/site.php');
    $original = file_get_contents($file);
    file_put_contents($file, $original."\n// edited\n");

    try {
        expect(fn () => app(ApproveTranslationFile::class)($review->fresh(), staff('editor_in_chief')))->toThrow(TranslationNotAllowed::class);

        app(SyncTranslationReviews::class)();
        $after = $review->fresh();

        expect($after->status)->toBe(TranslationReviewStatus::PendingReview)
            ->and($after->reviewer_id)->toBeNull()
            ->and($after->reviewed_at)->toBeNull()
            ->and($after->invalidated_at)->not->toBeNull()
            ->and($after->reviewed_strings)->toBe(0);
    } finally {
        file_put_contents($file, $original);
    }
});

it('leaves editorial publication to the gate, whatever the interface review says', function (): void {
    // Approving every interface file does not make an unreviewed article
    // translation publishable: the two are separate controls.
    app(SyncTranslationReviews::class)();
    TranslationReview::query()->where('locale', 'en')->each(fn ($r) => app(ApproveTranslationFile::class)($r, staff('editor_in_chief')));

    $source = publishableArticle();
    $draft = \App\Models\Article::factory()->draft()->create([
        'locale' => 'en', 'translation_group_id' => $source->translation_group_id,
        'source_article_id' => $source->id, 'translation_status' => \App\Enums\TranslationStatus::NeedsReview,
    ]);

    expect(array_column(app(\App\Actions\Articles\EvaluatePublishGate::class)->detailed($draft), 'rule'))->toContain('translation_review');
});
