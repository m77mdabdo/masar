<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes a set of cache tags, and carries nothing but strings.
 *
 * That is the whole point. The previous shape queued a listener that held an
 * Article, and a queued job holding a model re-fetches it when the worker picks
 * it up — so any job outliving its row dies on ModelNotFoundException. Draining
 * a real backlog for the first time produced 45 of exactly those, orphaned by a
 * `migrate:fresh` that rebuilt the database while Redis kept the queue.
 *
 * `$deleteWhenMissingModels` does not help a queued *listener*: the queue
 * reflects on the resolved job class, which for a listener is Laravel's
 * CallQueuedListener, not ours, so the flag is read off the wrong class and is
 * silently ignored. Removing the model is the fix that actually holds.
 */
class FlushCacheTags implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(public readonly array $tags) {}

    public function handle(): void
    {
        foreach ($this->tags as $tag) {
            Cache::tags($tag)->flush();
        }
    }
}
