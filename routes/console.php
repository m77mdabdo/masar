<?php

declare(strict_types=1);

use App\Console\Commands\CheckSources;
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
Schedule::command(CheckSources::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Retention. A publisher's body text is kept only as long as their terms allow,
 * and a dismissed item stays recoverable for a month. Neither window enforces
 * itself.
 */
Schedule::command(PurgeIntelligence::class)
    ->dailyAt('03:30')
    ->withoutOverlapping();
