<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Services\Translation\NullTranslationProvider;
use App\Services\Translation\SegmentGuard;
use App\Services\Translation\TranslationProvider;
use App\Support\TranslatableContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Fills a translation draft from its source, through the configured provider.
 *
 * Carries the draft's id, not the model (CLAUDE.md §3): a draft deleted while
 * the job waits is a job with nothing to do, not a ModelNotFoundException.
 *
 * - Unique per draft, so a double click or an overlapping auto-retranslate
 *   cannot run two translations of one draft at once.
 * - Rate limited across workers (`translation` limiter, config per_minute).
 * - Three tries with growing backoff; the last failure is recorded on the
 *   draft as `failed` with a message that never quotes content.
 * - A reviewed draft is never overwritten: a person's sign-off outranks any
 *   machine run (CLAUDE.md §5, editorial precedence).
 * - No text leaves the server unless `masar.translation.allow_external` is
 *   true, whatever provider is bound.
 */
final class TranslateArticle implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $draftId) {}

    public function uniqueId(): string
    {
        return (string) $this->draftId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('translation')];
    }

    public function handle(TranslationProvider $provider, TranslatableContent $content, SegmentGuard $guard): void
    {
        $draft = Article::query()->with(['source.blocks', 'blocks'])->find($this->draftId);

        if ($draft === null || $draft->source === null || $draft->translation_status?->isProtected()) {
            return;
        }

        $draft->forceFill(['translation_status' => TranslationStatus::Processing])->save();

        $source = $draft->source;
        $segments = $content->segments($source);

        if (! $provider instanceof NullTranslationProvider && ! config('masar.translation.allow_external')) {
            $provider = new NullTranslationProvider;
        }

        [$protected, $maps] = $guard->protect($segments, $source->locale);
        $result = $provider->translate($protected, $source->locale, $draft->locale, $this->glossaryFor($draft->locale));

        if ($result === null) {
            $draft->forceFill([
                'translation_status' => TranslationStatus::AwaitingTranslator,
                'translation_error' => null,
            ])->save();

            return;
        }

        $translated = $guard->restore($protected, $result->segments, $maps, $draft->locale);
        $content->apply($draft, $translated);

        $draft->forceFill([
            'translation_status' => TranslationStatus::NeedsReview,
            'translation_origin' => $provider->name(),
            'source_hash' => $content->hash($source),
            'translation_error' => null,
            'translated_at' => now(),
            'translation_attempts' => $draft->translation_attempts + 1,
        ])->save();

        activity('translation')->performedOn($draft)
            ->withProperties(['provider' => $provider->name(), 'segments' => count($translated), 'billed_characters' => $result->billedCharacters])
            ->log('translated');
    }

    public function failed(Throwable $e): void
    {
        Article::query()->whereKey($this->draftId)->update([
            'translation_status' => TranslationStatus::Failed->value,
            // Our own rejection messages never quote content; a provider's
            // exception might, so only its class is kept.
            'translation_error' => $e instanceof \App\Services\Translation\TranslationRejected
                ? mb_substr($e->getMessage(), 0, 500)
                : class_basename($e),
            'translation_attempts' => $this->attempts(),
        ]);
    }

    /** @return array<string, array<string, string>> */
    private function glossaryFor(string $locale): array
    {
        return array_filter(array_map(
            fn (array $renderings): array => array_intersect_key($renderings, [$locale => true]),
            (array) config('masar.translation.glossary'),
        ));
    }
}
