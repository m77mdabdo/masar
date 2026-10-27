<?php

declare(strict_types=1);

use App\Console\Commands\CheckSources;
use App\Console\Commands\FlushArticleViews;
use App\Console\Commands\PublishScheduledArticles;
use App\Console\Commands\PurgeIntelligence;
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

/*
 * Source polling. Every five minutes is the scheduler's granularity, not the
 * poll interval: each source decides its own frequency and the command only
 * dispatches the ones that are due.
 */
/*
 * Registered only when polling is enabled, rather than registered with a
 * `->when()` guard. `schedule:list` evaluates neither, so a guarded entry still
 * prints as though it runs — and a schedule that lies about what it does is the
 * thing this gate exists to prevent. When it is off, it is absent from the list.
 *
 * Off until the crawler's contact URL and mailbox resolve. Manual runs of
 * `masar:check-sources` are unaffected; this is the schedule only.
 */
if (config('masar.intelligence.polling_enabled')) {
    Schedule::command(CheckSources::class)
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->runInBackground();
}

/*
 * Readership lives in a Redis buffer between runs, so nothing increments a row
 * on every request (§5). Five minutes keeps the column close enough to the
 * truth and bounds what a Redis loss costs. (Ad impressions had a twin of this
 * entry; ads are out of launch scope — see DEFERRED.md.)
 */
Schedule::command(FlushArticleViews::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
 * Retention. A publisher's body text is kept only as long as their terms allow,
 * and a dismissed item stays recoverable for a month. Neither window enforces
 * itself.
 */
Schedule::command(PurgeIntelligence::class)
    ->dailyAt('03:30')
    ->withoutOverlapping();
