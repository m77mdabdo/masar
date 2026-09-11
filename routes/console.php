<?php

declare(strict_types=1);

use App\Console\Commands\PublishScheduledArticles;
use Illuminate\Support\Facades\Schedule;

/*
 * Scheduled publication runs every minute. `withoutOverlapping` matters: a slow
 * run must never have a second copy start behind it and publish the same
 * article twice.
 */
Schedule::command(PublishScheduledArticles::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
