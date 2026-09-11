<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SchedulePublication
{
    public function __construct(
        private readonly TransitionArticleStatus $transition,
    ) {}

    public function __invoke(
        Article $article,
        DateTimeInterface|string $scheduledFor,
        User $actor,
        ?string $note = null,
    ): Article {
        $when = Carbon::parse($scheduledFor);

        if ($when->isPast()) {
            throw new InvalidArgumentException(
                'scheduled_for must be in the future; received '.$when->toDateTimeString().'.',
            );
        }

        return DB::transaction(function () use ($article, $when, $actor, $note): Article {
            $article->scheduled_for = $when;
            $article->save();

            return ($this->transition)($article, ArticleStatus::Scheduled, $actor, $note);
        });
    }
}
