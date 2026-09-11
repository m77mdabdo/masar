<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Articles\SyncArticleTopics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every denormalised counter from source.
 *
 * Counters are maintained on the write path, which is narrow but not immune to
 * a bad import, a manual SQL fix or a bug. Drift happens; this is the way back.
 */
class RecountCommand extends Command
{
    protected $signature = 'masar:recount {--dry-run : Report drift without correcting it}';

    protected $description = 'Rebuild denormalised counters (mentions_count, articles_count) from source';

    public function handle(SyncArticleTopics $topics): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $drift = [
            'companies.mentions_count' => $this->driftFor('companies', 'mentions_count', 'company'),
            'people.mentions_count' => $this->driftFor('people', 'mentions_count', 'person'),
            'topics.articles_count' => $this->topicDrift(),
        ];

        foreach ($drift as $label => $count) {
            $this->line(sprintf('  %-28s %d row(s) out of date', $label, $count));
        }

        if ($dryRun) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($topics): void {
            $this->rebuildMentionCounts('companies', 'company');
            $this->rebuildMentionCounts('people', 'person');
            $topics->recountAll();
        });

        $this->info('Counters rebuilt.');

        return self::SUCCESS;
    }

    private function rebuildMentionCounts(string $table, string $morphKey): void
    {
        DB::update(
            "update `{$table}`
             set `mentions_count` = (
                 select count(*) from `entity_mentions`
                 where `entity_mentions`.`entity_type` = ?
                   and `entity_mentions`.`entity_id` = `{$table}`.`id`
             )",
            [$morphKey],
        );
    }

    private function driftFor(string $table, string $column, string $morphKey): int
    {
        return (int) DB::scalar(
            "select count(*) from `{$table}`
             where `{$column}` <> (
                 select count(*) from `entity_mentions`
                 where `entity_mentions`.`entity_type` = ?
                   and `entity_mentions`.`entity_id` = `{$table}`.`id`
             )",
            [$morphKey],
        );
    }

    private function topicDrift(): int
    {
        return (int) DB::scalar(
            'select count(*) from `topics`
             where `articles_count` <> (
                 select count(*) from `article_topic`
                 inner join `articles` on `articles`.`id` = `article_topic`.`article_id`
                 where `article_topic`.`topic_id` = `topics`.`id`
                   and `articles`.`status` = ?
                   and `articles`.`deleted_at` is null
                   and `articles`.`published_at` is not null
                   and `articles`.`published_at` <= ?
             )',
            ['published', now()->toDateTimeString()],
        );
    }
}
