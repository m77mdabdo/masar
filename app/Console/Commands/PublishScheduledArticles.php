<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Exceptions\PublishGateFailed;
use App\Models\Article;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes articles whose scheduled time has arrived.
 *
 * Goes through PublishArticle like every other publish — never a direct update.
 * That means the gate still runs: an article scheduled while complete and then
 * edited into an incomplete state will not quietly go live at 6am.
 */
class PublishScheduledArticles extends Command
{
    protected $signature = 'masar:publish-scheduled {--dry-run : List what would publish without publishing}';

    protected $description = 'Publish articles whose scheduled_for time has passed';

    public function handle(PublishArticle $publish): int
    {
        $due = Article::query()
            ->where('status', ArticleStatus::Scheduled)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $published = 0;
        $skipped = 0;
        $held = 0;
        $staleBefore = now()->subHours((int) config('masar.publish_gate.stale_schedule_hours', 24));

        foreach ($due as $article) {
            $actor = $this->actorFor($article);

            // Overdue past the threshold means the scheduler was not running
            // when the moment came. Publishing now would put the story out with
            // today's date on a decision made for another day, so it goes back
            // to an editor instead.
            if ($article->scheduled_for->lt($staleBefore)) {
                $held++;

                if ($this->option('dry-run')) {
                    $this->line("would hold #{$article->getKey()} — overdue since {$article->scheduled_for}");

                    continue;
                }

                $this->holdForReview($article, $actor);

                continue;
            }

            if ($actor === null) {
                $skipped++;
                Log::warning('Scheduled publish skipped: no actor with publish rights.', [
                    'article_id' => $article->getKey(),
                ]);

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would publish #{$article->getKey()} — {$article->title}");
                $published++;

                continue;
            }

            try {
                $publish($article, $actor, 'نشر تلقائي في الموعد المجدول');
                $published++;
            } catch (PublishGateFailed $e) {
                $skipped++;
                Log::warning('Scheduled publish blocked by the publish gate.', [
                    'article_id' => $article->getKey(),
                    'reasons' => $e->reasons(),
                ]);
            } catch (Throwable $e) {
                $skipped++;
                Log::error('Scheduled publish failed.', [
                    'article_id' => $article->getKey(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Published {$published}, skipped {$skipped}, held {$held} for review.");

        return self::SUCCESS;
    }

    /**
     * Returns a stale scheduled article to `ready` — unscheduled, not public,
     * back in the editors' queue — and records why, so the editor who finds it
     * there can see it missed its slot rather than wondering who unscheduled it.
     */
    private function holdForReview(Article $article, ?User $actor): void
    {
        $missed = $article->scheduled_for;

        if ($actor === null) {
            Log::warning('Stale scheduled article left scheduled: no actor with publish rights to return it.', [
                'article_id' => $article->getKey(),
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($article, $actor, $missed): void {
                $article->forceFill(['scheduled_for' => null])->save();

                app(TransitionArticleStatus::class)($article, ArticleStatus::Ready, $actor,
                    'فات موعد النشر المجدول ولم يُنشر تلقائياً — يحتاج قرار محرّر');

                activity()
                    ->performedOn($article)
                    ->causedBy($actor)
                    ->event('schedule_stale')
                    ->withProperties([
                        'scheduled_for' => $missed->toDateTimeString(),
                        'held_at' => now()->toDateTimeString(),
                        'threshold_hours' => (int) config('masar.publish_gate.stale_schedule_hours', 24),
                    ])
                    ->log('فات موعد النشر المجدول — أُعيدت للمراجعة دون نشر');
            });
        } catch (Throwable $e) {
            Log::error('Stale scheduled article could not be held for review.', [
                'article_id' => $article->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publishing happens as the person who made the decision.
     *
     * `scheduled_by_id` records exactly who that was. The editor/author chain
     * below is a fallback only for rows scheduled before that column existed —
     * inference is acceptable for legacy data, never for new decisions.
     */
    private function actorFor(Article $article): ?User
    {
        if ($article->scheduled_by_id !== null) {
            $scheduler = User::find($article->scheduled_by_id);

            if ($scheduler?->can('article.publish')) {
                return $scheduler;
            }
        }

        foreach ([$article->editor_id, $article->author_id] as $candidateId) {
            if ($candidateId === null) {
                continue;
            }

            $candidate = User::find($candidateId);

            if ($candidate?->can('article.publish')) {
                return $candidate;
            }
        }

        return User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'article.publish'))
            ->orderBy('id')
            ->first();
    }
}
