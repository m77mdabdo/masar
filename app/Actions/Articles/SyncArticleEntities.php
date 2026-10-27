<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Company;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Support\PublishedContent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewrites an article's edges in the content graph, and keeps the denormalised
 * counters honest in the same transaction.
 *
 * Counters are maintained here rather than in a model observer on purpose: the
 * write path is narrow and explicit, and an observer would hide the fact that
 * removing a mention has to touch a second table.
 */
class SyncArticleEntities
{
    /**
     * Only these carry a counter column. Countries, industries and markets are
     * aggregated on read — they are few and their pages are cached.
     *
     * @var array<string, class-string>
     */
    private const COUNTED = [
        'company' => Company::class,
        'person' => Person::class,
    ];

    /**
     * @param  array<int, array{type: string, id: int, role?: EntityRole|string, prominence?: int}>  $mentions
     */
    public function __invoke(Article $article, array $mentions): void
    {
        $rows = $this->normalise($mentions);

        DB::transaction(function () use ($article, $rows): void {
            // Every entity that could have its count changed: the ones we are
            // about to attach, plus the ones we are about to detach.
            $touched = $this->currentlyMentioned($article);

            $article->mentions()->delete();

            foreach ($rows as $row) {
                $article->mentions()->create([
                    'entity_type' => $row['type'],
                    'entity_id' => $row['id'],
                    'role' => $row['role'],
                    'prominence' => $row['prominence'],
                    'created_at' => $article->published_at ?? $article->created_at ?? now(),
                ]);

                $touched[$row['type']][] = $row['id'];
            }

            $this->recount($touched);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $mentions
     * @return array<int, array{type: string, id: int, role: string, prominence: int}>
     */
    private function normalise(array $mentions): array
    {
        $seen = [];
        $rows = [];

        foreach ($mentions as $mention) {
            $type = (string) ($mention['type'] ?? '');
            $id = (int) ($mention['id'] ?? 0);

            if ($type === '' || $id === 0) {
                throw new InvalidArgumentException('Each mention needs a type and an id.');
            }

            if (Relation::getMorphedModel($type) === null) {
                throw new InvalidArgumentException(
                    "Unknown entity type \"{$type}\". Register it in the morph map first.",
                );
            }

            // The unique index would reject a duplicate anyway; collapsing here
            // means a double-tagged company is a no-op instead of an exception.
            $key = $type.':'.$id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $role = $mention['role'] ?? EntityRole::Mentioned;
            $prominence = (int) ($mention['prominence'] ?? 0);

            $rows[] = [
                'type' => $type,
                'id' => $id,
                'role' => $role instanceof EntityRole ? $role->value : (string) $role,
                'prominence' => max(0, min(100, $prominence)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function currentlyMentioned(Article $article): array
    {
        $touched = [];

        foreach ($article->mentions()->get(['entity_type', 'entity_id']) as $mention) {
            $touched[$mention->entity_type][] = (int) $mention->entity_id;
        }

        return $touched;
    }

    /**
     * One bound UPDATE per entity type — never a query inside the loop over ids.
     *
     * @param  array<string, array<int, int>>  $touched
     */
    /**
     * Rebuild every counted entity's total, using the one definition of
     * published. `masar:recount` calls this rather than keeping its own copy of
     * the SQL — it had one, it was missing the published filter, and running the
     * command silently restored the inflated numbers this action had just
     * corrected.
     */
    public function recountAll(): int
    {
        // recount() takes [type => [ids]], the shape currentlyMentioned() builds.
        $touched = [];
        $total = 0;

        foreach (self::COUNTED as $type => $model) {
            $ids = $model::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $touched[$type] = $ids;
            $total += count($ids);
        }

        $this->recount($touched);

        return $total;
    }

    private function recount(array $touched): void
    {
        foreach ($touched as $type => $ids) {
            if (! isset(self::COUNTED[$type]) || $ids === []) {
                continue;
            }

            $ids = array_values(array_unique($ids));

            /** @var class-string<Model> $model */
            $model = self::COUNTED[$type];

            // Table name comes from our own constant map, never from input.
            $table = (new $model)->getTable();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            /*
             * Published content only (§5), using the same definition the topic
             * counter uses. This counted every mention including drafts, so an
             * entity we had only ever named in an unpublished article presented
             * as one we cover — and `ComposeHomepage` filters the entities
             * section on this column, so it decided homepage content too.
             *
             * Both content types are counted, because a mention on a published
             * opportunity is coverage as much as one on an article.
             */
            [$articlePublished, $articleBindings] = PublishedContent::articleCondition();
            [$opportunityPublished, $opportunityBindings] = PublishedContent::opportunityCondition();

            DB::update(
                "update `{$table}`
                 set `mentions_count` = (
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
                 )
                 where `id` in ({$placeholders})",
                [$type, ...$articleBindings, ...$opportunityBindings, ...$ids],
            );
        }
    }
}
