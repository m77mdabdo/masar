<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Company;
use App\Models\EntityMention;
use App\Models\Person;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything an entity page knows that no other page on the site can answer.
 *
 * A list of articles about a company is not a moat — anyone with a CMS and a
 * tag can produce one. What the graph can answer and a tag cannot is: how has
 * our coverage of this subject been distributed, over what period, and **who
 * else appears in the stories where it appears**. Anyone can list every article
 * mentioning Aramco; knowing that Aramco and SABIC appeared together in seven
 * of them, and who else was in the room, is what `entity_mentions` being
 * polymorphic actually buys.
 *
 * Works for any mentionable entity — company, person, topic — because the
 * question is the same one in every case.
 *
 * Every method here is bounded SQL — grouped counts and one hydration pass per
 * entity type. No query runs inside a loop (§5).
 */
final class EntityProfileQuery
{
    private readonly string $type;

    private readonly int|string $id;

    public function __construct(private readonly Model $entity)
    {
        // The morph alias, not the class name: `entity_mentions` stores short
        // keys ('company', 'person') and enforceMorphMap rejects anything else.
        $this->type = $entity->getMorphClass();
        $this->id = $entity->getKey();
    }

    public static function for(Model $entity): self
    {
        return new self($entity);
    }

    /**
     * Published coverage broken down by what kind of thing it is.
     *
     * @return Collection<int, array{label: string, count: int}>
     */
    public function coverage(): Collection
    {
        $articles = DB::table('entity_mentions')
            ->join('articles', function ($join): void {
                $join->on('articles.id', '=', 'entity_mentions.mentionable_id')
                    ->where('entity_mentions.mentionable_type', '=', 'article');
            })
            ->where('entity_mentions.entity_type', $this->type)
            ->where('entity_mentions.entity_id', $this->id)
            ->where('articles.status', 'published')
            // `key` is reserved in MySQL; the alias is spelled out instead.
            ->selectRaw('articles.content_type as content_type, COUNT(*) as total')
            ->groupBy('articles.content_type')
            ->pluck('total', 'content_type');

        $opportunities = (int) DB::table('entity_mentions')
            ->join('opportunities', function ($join): void {
                $join->on('opportunities.id', '=', 'entity_mentions.mentionable_id')
                    ->where('entity_mentions.mentionable_type', '=', 'opportunity');
            })
            ->where('entity_mentions.entity_type', $this->type)
            ->where('entity_mentions.entity_id', $this->id)
            ->count();

        $rows = $articles
            ->map(fn (int $total, string $key): array => [
                'label' => $this->contentTypeLabel($key),
                'count' => $total,
            ])
            ->values();

        if ($opportunities > 0) {
            $rows->push(['label' => __('pages.opportunities.title'), 'count' => $opportunities]);
        }

        return $rows->sortByDesc('count')->values();
    }

    /**
     * Coverage per year, oldest first — how long we have been following this.
     *
     * @return Collection<int, array{year: int, count: int}>
     */
    public function timeline(): Collection
    {
        return DB::table('entity_mentions')
            ->join('articles', function ($join): void {
                $join->on('articles.id', '=', 'entity_mentions.mentionable_id')
                    ->where('entity_mentions.mentionable_type', '=', 'article');
            })
            ->where('entity_mentions.entity_type', $this->type)
            ->where('entity_mentions.entity_id', $this->id)
            ->where('articles.status', 'published')
            ->whereNotNull('articles.published_at')
            ->selectRaw('YEAR(articles.published_at) as year, COUNT(*) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn (object $row): array => ['year' => (int) $row->year, 'count' => (int) $row->total])
            ->values();
    }

    /**
     * Who else turns up in the same stories.
     *
     * The query a tag cannot answer: take every published piece mentioning this
     * company, then every *other* entity mentioned in those same pieces, and
     * rank by how often they co-occur. Two statements, no loop.
     *
     * @return Collection<string, Collection<int, array{name: string, slug: string, count: int}>>
     */
    public function coMentions(int $perType = 6): Collection
    {
        $mine = DB::table('entity_mentions')
            ->where('entity_type', $this->type)
            ->where('entity_id', $this->id)
            ->select('mentionable_type', 'mentionable_id');

        $others = EntityMention::query()
            ->joinSub($mine, 'mine', function ($join): void {
                $join->on('entity_mentions.mentionable_type', '=', 'mine.mentionable_type')
                    ->on('entity_mentions.mentionable_id', '=', 'mine.mentionable_id');
            })
            ->whereIn('entity_mentions.entity_type', ['company', 'person', 'topic'])
            // Not itself.
            ->where(fn ($q) => $q
                ->where('entity_mentions.entity_type', '!=', $this->type)
                ->orWhere('entity_mentions.entity_id', '!=', $this->id))
            ->selectRaw('entity_mentions.entity_type, entity_mentions.entity_id, COUNT(*) as total')
            ->groupBy('entity_mentions.entity_type', 'entity_mentions.entity_id')
            ->orderByDesc('total')
            ->limit($perType * 3)
            ->get();

        // One hydration query per type, not one per row.
        $models = [
            'company' => Company::query()->whereIn('id', $others->where('entity_type', 'company')->pluck('entity_id'))->with('translations')->get()->keyBy('id'),
            'person' => Person::query()->whereIn('id', $others->where('entity_type', 'person')->pluck('entity_id'))->with('translations')->get()->keyBy('id'),
            'topic' => Topic::query()->whereIn('id', $others->where('entity_type', 'topic')->pluck('entity_id'))->with('translations')->get()->keyBy('id'),
        ];

        return $others
            ->groupBy('entity_type')
            ->map(fn (Collection $rows, string $type): Collection => $rows
                ->map(function (object $row) use ($models, $type): ?array {
                    $model = $models[$type][$row->entity_id] ?? null;

                    return $model === null ? null : [
                        'name' => (string) $model->name,
                        'slug' => (string) $model->slug,
                        'count' => (int) $row->total,
                    ];
                })
                ->filter()
                ->take($perType)
                ->values())
            ->filter(fn (Collection $rows): bool => $rows->isNotEmpty());
    }

    /**
     * The sections this subject turns up in — what they actually cover.
     *
     * @return Collection<int, array{name: string, slug: string, count: int}>
     */
    public function sections(int $limit = 6): Collection
    {
        return DB::table('entity_mentions')
            ->join('articles', function ($join): void {
                $join->on('articles.id', '=', 'entity_mentions.mentionable_id')
                    ->where('entity_mentions.mentionable_type', '=', 'article');
            })
            ->join('categories', 'categories.id', '=', 'articles.category_id')
            ->join('category_translations', function ($join): void {
                $join->on('category_translations.category_id', '=', 'categories.id')
                    ->where('category_translations.locale', '=', app()->getLocale());
            })
            ->where('entity_mentions.entity_type', $this->type)
            ->where('entity_mentions.entity_id', $this->id)
            ->where('articles.status', 'published')
            ->selectRaw('categories.slug as slug, category_translations.name as name, COUNT(*) as total')
            ->groupBy('categories.slug', 'category_translations.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'count' => (int) $row->total,
            ])
            ->values();
    }

    /**
     * The entities that appear most across a given set of articles.
     *
     * Topics link through `article_topic`, not `entity_mentions`, so a topic is
     * not a mentionable entity and cannot answer the co-mention question the
     * same way. The useful question on a topic page is the other direction:
     * given these articles, who turns up in them. One grouped count and one
     * hydration pass per type.
     *
     * @param  \Illuminate\Database\Query\Builder  $articleIds  a subquery selecting article ids
     * @return Collection<string, Collection<int, array{name: string, slug: string, count: int}>>
     */
    public static function entitiesInArticles($articleIds, int $perType = 6): Collection
    {
        $rows = DB::table('entity_mentions')
            ->whereIn('mentionable_id', $articleIds)
            ->where('mentionable_type', 'article')
            ->whereIn('entity_type', ['company', 'person'])
            ->selectRaw('entity_type, entity_id, COUNT(*) as total')
            ->groupBy('entity_type', 'entity_id')
            ->orderByDesc('total')
            ->limit($perType * 2)
            ->get();

        $models = [
            'company' => Company::query()->whereIn('id', $rows->where('entity_type', 'company')->pluck('entity_id'))->with('translations')->get()->keyBy('id'),
            'person' => Person::query()->whereIn('id', $rows->where('entity_type', 'person')->pluck('entity_id'))->with('translations')->get()->keyBy('id'),
        ];

        return $rows
            ->groupBy('entity_type')
            ->map(fn (Collection $group, string $type): Collection => $group
                ->map(function (object $row) use ($models, $type): ?array {
                    $model = $models[$type][$row->entity_id] ?? null;

                    return $model === null ? null : [
                        'name' => (string) $model->name,
                        'slug' => (string) $model->slug,
                        'count' => (int) $row->total,
                    ];
                })
                ->filter()
                ->take($perType)
                ->values())
            ->filter(fn (Collection $group): bool => $group->isNotEmpty());
    }

    private function contentTypeLabel(string $key): string
    {
        $type = \App\Enums\ContentType::tryFrom($key);

        return $type?->label() ?? $key;
    }
}
