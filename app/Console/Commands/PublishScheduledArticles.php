<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Articles\PublishArticle;
use App\Enums\ArticleStatus;
use App\Exceptions\PublishGateFailed;
use App\Models\Article;
use App\Models\User;
use Illuminate\Console\Command;
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

        foreach ($due as $article) {
            $actor = $this->actorFor($article);

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

        $this->info("Published {$published}, skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Publishing happens as the person who owns the decision.
     *
     * The editor is the right actor — they scheduled it, and they hold the
     * publish permission. We fall back to any user who can publish so a missing
     * editor does not strand a scheduled story.
     *
     * A `scheduled_by_id` column would make this exact rather than inferred;
     * see the note in the task report.
     */
    private function actorFor(Article $article): ?User
    {
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
