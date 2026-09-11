<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Publishing has several side effects — cache flush, search reindex, newsletter
 * queue, push, audit. They hang off this event rather than living inside the
 * Action, so a failure in one cannot roll back the publish itself.
 */
class ArticlePublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Article $article,
        public readonly User $actor,
    ) {}
}
