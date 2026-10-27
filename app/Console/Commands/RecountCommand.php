<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleTopics;
use App\Actions\Articles\UpdateGateFailuresCount;
use Illuminate\Console\Command;
use App\Support\PublishedContent;
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

    public function handle(
        SyncArticleTopics $topics,
        SyncArticleEntities $entities,
        UpdateGateFailuresCount $gateCount,
    ): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $drift = [
            'companies.mentions_count' => $this->driftFor('companies', 'mentions_count', 'company'),
            'people.mentions_count' => $this->driftFor('people', 'mentions_count', 'person'),
            'topics.articles_count' => $this->topicDrift(),
            'articles.gate_failures_count' => $this->gateDrift(),
        ];

        foreach ($drift as $label => $count) {
            $this->line(sprintf('  %-28s %d row(s) out of date', $label, $count));
        }

        if ($dryRun) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($topics, $entities): void {
            // The actions own their counters. This command had its own copy of
            // the SQL, it was missing the published filter, and running it
            // silently restored the inflated numbers the action had just fixed.
            $entities->recountAll();
            $topics->recountAll();
        });

        // Outside the transaction: the gate is PHP and evaluates every article,
        // so holding a write lock across it would block the newsroom.
        $gateCount->all();

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

    /**
     * Drift measured against the same definition of published the counter
     * writes. It was measured against an unfiltered count, so it agreed with
     * the bug and reported zero rows out of date on data where every row was
     * wrong.
     */
    private function driftFor(string $table, string $column, string $morphKey): int
    {
        [$articlePublished, $articleBindings] = PublishedContent::articleCondition();
        [$opportunityPublished, $opportunityBindings] = PublishedContent::opportunityCondition();

        return (int) DB::scalar(
            "select count(*) from `{$table}`
             where `{$column}` <> (
                 select count(*) from `entity_mentions`
                 where `entity_mentions`.`entity_type` = ?
                   and `entity_mentions`.`entity_id` = `{$table}`.`id`
                   and (
                       (
                           `entity_mentions`.`mentionable_type` = 'article'
                           and exists (
                               select 1 from `articles`
                               where `articles`.`id` = `entity_mentions`.`mentionable_id`
                                 and {$articlePublished}
                           )
                       )
                       or (
                           `entity_mentions`.`mentionable_type` = 'opportunity'
                           and exists (
                               select 1 from `opportunities`
                               where `opportunities`.`id` = `entity_mentions`.`mentionable_id`
                                 and {$opportunityPublished}
                           )
                       )
                   )
             )",
            [$morphKey, ...$articleBindings, ...$opportunityBindings],
        );
    }

    /**
     * NULL counts as drift: it means the gate has never been evaluated for that
     * row, which is exactly what this command exists to fix.
     */
    private function gateDrift(): int
    {
        return (int) DB::scalar('select count(*) from `articles` where `gate_failures_count` is null');
    }

    private function topicDrift(): int
    {
        [$published, $bindings] = PublishedContent::articleCondition();

        return (int) DB::scalar(
            "select count(*) from `topics`
             where `articles_count` <> (
                 select count(*) from `article_topic`
                 inner join `articles` on `articles`.`id` = `article_topic`.`article_id`
                 where `article_topic`.`topic_id` = `topics`.`id`
                   and {$published}
             )",
            $bindings,
        );
    }
}
