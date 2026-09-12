<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Article;
use App\Models\EntityMention;
use App\Models\Opportunity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Everything published that mentions a given entity, newest first, across every
 * content type at once.
 *
 * This is the reason entity_mentions is doubly polymorphic. /ar/companies/aramco
 * is one indexed query plus one eager load per content type, rather than a
 * separate query per type unioned in PHP — and it keeps working when reports and
 * videos are added, without touching this class.
 *
 * Ordering uses entity_mentions.created_at, which PublishArticle realigns to the
 * article's published_at at the moment of publication. The sort is therefore
 * publication order, served by the same composite index that does the filtering
 * — no join to the content table required.
 */
class EntityContentQuery
{
    /**
     * Content types that can mention an entity. `report` and `video` join this
     * list when those models exist.
     *
     * @var array<int, class-string<Model>>
     */
    private const CONTENT_TYPES = [
        Article::class,
        Opportunity::class,
    ];

    private Builder $query;

    public function __construct(private readonly Model $entity)
    {
        $this->query = EntityMention::query()
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', $entity->getKey())
            ->whereHasMorph(
                'mentionable',
                self::CONTENT_TYPES,
                fn (Builder $q) => $q->published(),
            )
            ->with(['mentionable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Article::class => ['category:id,slug,color', 'category.translations', 'author:id,name', 'heroMedia'],
                Opportunity::class => ['industry.translations', 'country.translations'],
            ])])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public static function for(Model $entity): self
    {
        return new self($entity);
    }

    /**
     * Apply the published filter and eager loads to a query that is already
     * scoped to an entity's mentions — a Filament relation manager, typically.
     *
     * Sharing this with the public-facing query means the admin listing and the
     * company page cannot drift apart, and a performance problem shows up in
     * both at once rather than only after launch.
     */
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->whereHasMorph(
                'mentionable',
                self::CONTENT_TYPES,
                fn (Builder $q) => $q->published(),
            )
            ->with(['mentionable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Article::class => ['category:id,slug,color', 'category.translations', 'author:id,name'],
                Opportunity::class => ['industry.translations', 'country.translations'],
            ])]);
    }

    /**
     * Narrow to a single content type, e.g. only articles on a company page tab.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function ofType(string $modelClass): self
    {
        $this->query->where('mentionable_type', (new $modelClass)->getMorphClass());

        return $this;
    }

    /**
     * Only the pieces where this entity is central, for a "main coverage" rail.
     */
    public function prominentOnly(int $minimum = 70): self
    {
        $this->query->where('prominence', '>=', $minimum);

        return $this;
    }

    public function inLocale(string $locale): self
    {
        $this->query->whereHasMorph(
            'mentionable',
            self::CONTENT_TYPES,
            fn (Builder $q) => $q->where('locale', $locale),
        );

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * The mention rows, each with `mentionable` loaded.
     *
     * @return Collection<int, EntityMention>
     */
    public function mentions(): Collection
    {
        return $this->query->get();
    }

    /**
     * Just the content itself, which is what a template usually wants.
     *
     * @return Collection<int, Model>
     */
    public function content(): Collection
    {
        return $this->mentions()
            ->map(fn (EntityMention $mention): ?Model => $mention->mentionable)
            ->filter()
            ->values();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    public function count(): int
    {
        return $this->query->count();
    }
}
